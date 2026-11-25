<div class="container-fluid px-0">
    <?php $canEditPurchaseStock = !empty($canEditPurchaseStock); ?>
    <style>
        .compact-purchase-stock-table > :not(caption) > * > * {
            padding: 0.25rem 0.5rem;
            vertical-align: middle;
        }
        .compact-purchase-stock-table tbody .badge {
            font-size: 0.68rem;
        }
        .compact-purchase-stock-table .btn-sm {
            line-height: 1;
        }
    </style>
    <!-- Page Header & Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-2.5 d-flex align-items-center justify-content-center text-white shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); width: 48px; height: 48px;">
                <i class="bi bi-boxes fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Purchase Stocks & Inventory</h4>
                <p class="text-muted small mb-0">Monitor inwarded fabric rolls, trims/garment supplies, and finished goods inventory with current stock balance.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php if (\App\Core\Auth::can('purchase_stocks', 'export')): ?>
            <a href="<?= \App\Core\Controller::url('/m/purchase_stocks/export?tab=' . $activeTab . ($selectedCid ? '&concern_id=' . $selectedCid : '')) ?>" class="btn btn-outline-success btn-sm px-3 shadow-sm d-flex align-items-center gap-1.5 rounded-2">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export <?= match($activeTab) { 'rolls' => 'Rolls', 'garment_other' => 'Trims', 'finished_goods' => 'Finished Goods', default => 'Stocks' } ?> (CSV)
            </a>
            <?php endif; ?>
            <a href="<?= \App\Core\Controller::url('/m/label_generation?tab=' . match($activeTab) { 'rolls' => 'purchase_raw', 'garment_other' => 'purchase_raw', 'finished_goods' => 'purchase_fg', default => 'purchase_raw' }) ?>" class="btn btn-outline-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-1.5 rounded-2">
                <i class="bi bi-qr-code"></i> Print Labels
            </a>
            <?php if (\App\Core\Auth::can('purchase_billing', 'create')): ?>
            <a href="<?= \App\Core\Controller::url('/m/purchase_billing/create') ?>" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-1.5 rounded-2">
                <i class="bi bi-bag-plus"></i> Record Purchase
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Super Admin Concern Switcher Banner (if Super Admin) -->
    <?php if (App\Core\Auth::isSuperAdmin() && !empty($allConcerns)): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
            <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-building-gear text-primary fs-5"></i>
                    <div>
                        <div class="fw-bold small text-dark">Filter Purchase Inventory by Concern:</div>
                        <div class="text-muted" style="font-size: 0.76rem;">Viewing <?= $selectedCid ? 'specific concern stock ledger' : 'all concerns consolidated stock ledger' ?></div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=' . $activeTab) ?>" class="btn btn-sm <?= empty($selectedCid) ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-2 px-3 fw-semibold">
                        All Concerns
                    </a>
                    <?php foreach ($allConcerns as $con): ?>
                        <a href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=' . $activeTab . '&concern_id=' . $con['id']) ?>" class="btn btn-sm <?= ($selectedCid == $con['id']) ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-2 px-3 fw-semibold">
                            <?= htmlspecialchars($con['code']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4 High-Impact KPI Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- Metric 1: Fabric Rolls Stock -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.05em;">Fabric Rolls Stock</div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($metrics['rolls_qty'], 0) ?> <span class="fs-6 fw-normal text-muted">Rolls</span></h4>
                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-layers text-primary me-1"></i><strong><?= number_format($metrics['rolls_weight'], 2) ?> kg</strong> total fabric
                        </div>
                    </div>
                    <div class="rounded-3 p-3 bg-primary-subtle text-primary">
                        <i class="bi bi-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 2: Trims & Garment Other -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.05em;">Trims & Garment Other</div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($metrics['other_qty'], 0) ?> <span class="fs-6 fw-normal text-muted">Units</span></h4>
                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-box text-info me-1"></i><strong><?= $metrics['other_count'] ?></strong> distinct supply items
                        </div>
                    </div>
                    <div class="rounded-3 p-3 bg-info-subtle text-info">
                        <i class="bi bi-scissors fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 3: Finished Goods Stock -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-purple" style="border-left-color: #8b5cf6 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.05em;">Purchased Finished Goods</div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($metrics['finished_qty'], 0) ?> <span class="fs-6 fw-normal text-muted">Pcs</span></h4>
                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-tag text-purple me-1" style="color: #8b5cf6;"></i>MRP Val: <strong>₹<?= number_format($metrics['finished_mrp_val'], 2) ?></strong>
                        </div>
                    </div>
                    <div class="rounded-3 p-3 text-purple" style="background: #ede9fe; color: #6d28d9;">
                        <i class="bi bi-bag-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 4: Total Purchased Inventory Value -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.05em;">Total Stock Valuation</div>
                        <h4 class="fw-bold mb-0 text-success">₹<?= number_format($metrics['grand_value'], 2) ?></h4>
                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-check2-circle text-success me-1"></i>Across all 3 purchase categories
                        </div>
                    </div>
                    <div class="rounded-3 p-3 bg-success-subtle text-success">
                        <i class="bi bi-currency-rupee fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Inventory Card with 3 Sections / Tabs -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <!-- Section Navigation Pills (3 Tabs) -->
        <div class="card-header bg-white border-bottom pt-3 pb-0 px-4">
            <ul class="nav nav-pills card-header-pills gap-2 pb-3 mb-0" id="stockSectionTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link py-2 px-3 fw-semibold d-flex align-items-center gap-2 rounded-2 <?= $activeTab === 'rolls' ? 'active' : '' ?>" 
                       href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=rolls' . ($selectedCid ? '&concern_id=' . $selectedCid : '')) ?>">
                        <i class="bi bi-stack"></i>
                        <span>1. Fabric Rolls</span>
                        <span class="badge <?= $activeTab === 'rolls' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill ms-1">
                            <?= count($rolls) ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 px-3 fw-semibold d-flex align-items-center gap-2 rounded-2 <?= $activeTab === 'garment_other' ? 'active' : '' ?>" 
                       href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=garment_other' . ($selectedCid ? '&concern_id=' . $selectedCid : '')) ?>">
                        <i class="bi bi-scissors"></i>
                        <span>2. Other Garments & Trims</span>
                        <span class="badge <?= $activeTab === 'garment_other' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill ms-1">
                            <?= count($garmentOther) ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 px-3 fw-semibold d-flex align-items-center gap-2 rounded-2 <?= $activeTab === 'finished_goods' ? 'active' : '' ?>" 
                       href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=finished_goods' . ($selectedCid ? '&concern_id=' . $selectedCid : '')) ?>">
                        <i class="bi bi-bag-check"></i>
                        <span>3. Purchased Finished Goods</span>
                        <span class="badge <?= $activeTab === 'finished_goods' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill ms-1">
                            <?= count($finishedGoods) ?>
                        </span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 border-bottom bg-light">
            <form method="GET" action="<?= \App\Core\Controller::url('/m/purchase_stocks') ?>" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                <?php if ($selectedCid): ?>
                    <input type="hidden" name="concern_id" value="<?= (int)$selectedCid ?>">
                <?php endif; ?>

                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Search by Code, Name, Pattern, Color, Bill #..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>

                <div class="col-6 col-md-4">
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">-- All Suppliers --</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= ($supplierFilter == $sup['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sup['name']) ?> <?= !empty($sup['company_name']) ? ('(' . htmlspecialchars($sup['company_name']) . ')') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3 rounded-2 fw-semibold shadow-sm">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($supplierFilter)): ?>
                        <a href="<?= \App\Core\Controller::url('/m/purchase_stocks?tab=' . $activeTab . ($selectedCid ? '&concern_id=' . $selectedCid : '')) ?>" class="btn btn-sm btn-outline-secondary px-2 rounded-2">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ============================================== -->
        <!-- TAB SECTION 1: FABRIC ROLLS                    -->
        <!-- ============================================== -->
        <?php if ($activeTab === 'rolls'): ?>
            <div class="card-body p-2 px-3 bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small"><i class="bi bi-info-circle text-primary me-1"></i> Duplicate entries automatically accumulated with previous count.</span>
                </div>
            </div>

            <!-- View A: Consolidated Color & Design Stock (Duplicates removed, Qty added to previous count) -->
            <div id="viewRollConsolidated" class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered table-sm align-middle mb-0 text-center small">
                        <thead class="table-light">
                            <tr class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                                <th style="width: 40px;">#</th>
                                <th class="text-start">Design Name / Pattern</th>
                                <th>Color / Shade</th>
                                <th>HSN</th>
                                <th class="table-primary text-primary fw-bold">Accumulated Stock</th>
                                <th>Total Fabric Weight</th>
                                <th class="text-end">Unit Rate</th>
                                <th class="text-end">Total Valuation (₹)</th>
                                <th class="text-start">Inward History</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($consolidatedRolls)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="bi bi-stack display-6 text-secondary d-block mb-2"></i>
                                        <strong>No fabric roll specifications registered yet.</strong>
                                        <div class="small mt-1">Record a purchase bill and click <strong>Add to Stock</strong> in Label Generation to automatically populate the Color & Design roll stock registry.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $cIdx = 1; foreach ($consolidatedRolls as $cr): ?>
                                    <?php
                                    $labelFabric = $cr['last_item_name'] ?: 'Fabric Material';
                                    $labelColor = $cr['last_item_color'] ?: 'STANDARD';
                                    $labelDesign = trim($cr['last_item_pattern'] ?: ($cr['last_item_specification'] ?: 'PLAIN'));
                                    $labelPrice = (float)($cr['last_item_unit_price'] ?: ($cr['last_item_mrp'] ?: 0));
                                    $labelHeader = 'ROLL #' . ($cr['last_item_code'] ?: $cr['last_item_id']);
                                    $labelRef = $cr['last_bill_no'] ?: ('PO-' . ($cr['last_item_bill_id'] ?? ''));
                                    $labelConcern = \App\Core\Auth::concern()['name'] ?? 'GRM Inventory';
                                    $qrPayload = \App\Services\QrService::generateTagCode(
                                        $cr['last_item_name'] ?: 'Fabric',
                                        $cr['last_item_color'] ?? '',
                                        $labelDesign,
                                        (string)($cr['last_item_id'] ?? '')
                                    );
                                    ?>
                                    <tr>
                                        <td class="text-muted fw-semibold py-1 px-2"><?= $cIdx++ ?></td>
                                        <td class="text-start py-1 px-2">
                                            <div class="fw-bold text-dark font-monospace">
                                                <i class="bi bi-palette text-primary me-1"></i><?= htmlspecialchars($cr['design_name']) ?>
                                            </div>
                                        </td>
                                        <td class="py-1 px-2">
                                            <span class="badge bg-light text-dark border px-1 py-1 text-uppercase fw-semibold">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.55rem; color: #4f46e5;"></i><?= htmlspecialchars($cr['color']) ?>
                                            </span>
                                        </td>
                                        <td class="font-monospace small py-1 px-2"><?= htmlspecialchars($cr['hsn_code'] ?: '-') ?></td>
                                        <td class="table-primary text-primary fw-bold py-1 px-2">
                                            <?= (float)$cr['qty'] ?> <span class="fw-normal text-muted"><?= htmlspecialchars($cr['unit'] ?: 'Rolls') ?></span>
                                        </td>
                                        <td class="fw-bold text-secondary py-1 px-2">
                                            <?= number_format((float)$cr['net_weight'], 2) ?> kg
                                        </td>
                                        <td class="text-end fw-semibold text-dark py-1 px-2">
                                            ₹<?= number_format((float)$cr['last_unit_price'], 2) ?>
                                        </td>
                                        <td class="text-end fw-bold text-dark py-1 px-2">
                                            ₹<?= number_format((float)$cr['total_value'], 2) ?>
                                        </td>
                                        <td class="text-start py-1 px-2">
                                            <span class="fw-semibold text-dark"><i class="bi bi-receipt text-secondary me-1"></i><?= (int)$cr['bills_count'] ?> batch(es)</span>
                                            <?php if (!empty($cr['last_bill_no'])): ?>
                                                <div class="text-muted small">
                                                    <span class="font-monospace fw-semibold text-primary"><?= htmlspecialchars($cr['last_bill_no']) ?></span>
                                                    <?= !empty($cr['last_supplier_name']) ? (' &bull; ' . htmlspecialchars($cr['last_supplier_name'])) : '' ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-1 px-2">
                                            <span class="badge bg-<?= (float)$cr['qty'] > 0 ? 'success' : 'secondary' ?> text-white px-2 py-1">
                                                <?= (float)$cr['qty'] > 0 ? 'IN STOCK' : 'OUT OF STOCK' ?>
                                            </span>
                                        </td>
                                        <td class="py-1 px-2">
                                            <div class="d-flex justify-content-center gap-1">
                                            <button type="button" class="btn btn-outline-primary btn-sm p-1"
                                                data-qr="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>"
                                                data-label-concern="<?= htmlspecialchars($labelConcern, ENT_QUOTES, 'UTF-8') ?>"
                                                data-label-ref="<?= htmlspecialchars($labelRef, ENT_QUOTES, 'UTF-8') ?>"
                                                data-label-title="<?= htmlspecialchars($labelHeader, ENT_QUOTES, 'UTF-8') ?>"
                                                data-label-fabric="<?= htmlspecialchars($labelFabric, ENT_QUOTES, 'UTF-8') ?>"
                                                data-label-color="<?= htmlspecialchars($labelColor, ENT_QUOTES, 'UTF-8') ?>"
                                                data-label-design="<?= htmlspecialchars($labelDesign, ENT_QUOTES, 'UTF-8') ?>"
                                                data-label-price="<?= htmlspecialchars('₹' . number_format($labelPrice, 2), ENT_QUOTES, 'UTF-8') ?>"
                                                data-label-code="<?= htmlspecialchars($qrPayload, ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="showRollQr(this)"
                                                title="Preview the exact fabric roll label">
                                                <i class="bi bi-qr-code"></i>
                                            </button>
                                            <?php if ($canEditRollStock): ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm p-1"
                                                    data-concern-id="<?= (int)$cr['concern_id'] ?>"
                                                    data-source-color="<?= htmlspecialchars($cr['source_color'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-source-design="<?= htmlspecialchars($cr['source_design_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-color="<?= htmlspecialchars($cr['color'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-design="<?= htmlspecialchars($cr['design_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-hsn="<?= htmlspecialchars($cr['hsn_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-qty="<?= htmlspecialchars(number_format((float)$cr['qty'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-weight="<?= htmlspecialchars(number_format((float)$cr['net_weight'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-unit="<?= htmlspecialchars($cr['unit'] ?: 'Rolls', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-rate="<?= htmlspecialchars(number_format((float)$cr['last_unit_price'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-total="<?= htmlspecialchars(number_format((float)$cr['total_value'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    onclick="editConsolidatedRoll(this)"
                                                    title="Edit consolidated fabric stock">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <style>
                #rollQrModal .label-sticker {
                    width: 100%;
                    max-width: 420px;
                    min-height: 140px;
                    background: #fff;
                    border: 1.5px solid #0f172a;
                    border-radius: 10px;
                    padding: 8px 12px;
                    display: flex;
                    flex-direction: column;
                    box-sizing: border-box;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                    color: #0f172a;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                }
                #rollQrModal .label-header-bar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 6px;
                    padding-bottom: 4px;
                }
                #rollQrModal .concern-check-icon {
                    width: 17px;
                    height: 17px;
                    margin-right: 6px;
                    border-radius: 4px;
                    background: #0d6efd;
                    color: #fff;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    font-size: 11px;
                }
                #rollQrModal .concern-name-text {
                    max-width: 170px;
                    overflow: hidden;
                    color: #0f172a;
                    font-size: 0.82rem;
                    font-weight: 800;
                    letter-spacing: 0.04em;
                    text-overflow: ellipsis;
                    text-transform: uppercase;
                    white-space: nowrap;
                }
                #rollQrModal .ref-pill-badge {
                    padding: 1px 7px;
                    border: 1px solid #bae6fd;
                    border-radius: 12px;
                    background: #e0f2fe;
                    color: #0284c7;
                    font-family: monospace;
                    font-size: 0.70rem;
                    font-weight: 600;
                    white-space: nowrap;
                }
                #rollQrModal .label-divider {
                    margin: 4px 0 6px;
                    border-top: 1px dashed #cbd5e1;
                }
                #rollQrModal .label-body-content {
                    display: flex;
                    flex: 1;
                    align-items: center;
                    gap: 10px;
                }
                #rollQrModal .label-qr-wrapper,
                #rollQrModal .label-qr-box {
                    width: 76px;
                    height: 76px;
                    flex-shrink: 0;
                }
                #rollQrModal .label-qr-wrapper {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #fff;
                }
                #rollQrModal .label-details-stack {
                    flex: 1;
                    overflow: hidden;
                    font-size: 0.76rem;
                    line-height: 1.35;
                }
                #rollQrModal .label-item-title {
                    margin-bottom: 2px;
                    overflow: hidden;
                    color: #0f172a;
                    font-size: 0.86rem;
                    font-weight: 800;
                    text-overflow: ellipsis;
                    text-transform: uppercase;
                    white-space: nowrap;
                }
                #rollQrModal .label-detail-row {
                    overflow: hidden;
                    color: #334155;
                    font-size: 0.75rem;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                #rollQrModal .detail-label {
                    margin-right: 3px;
                    color: #64748b;
                    font-weight: 500;
                }
                #rollQrModal .detail-val {
                    color: #0f172a;
                    font-weight: 600;
                }
            </style>
            <?php if ($canEditRollStock): ?>
                <div class="modal fade" id="editConsolidatedRollModal" tabindex="-1" aria-labelledby="editConsolidatedRollModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <form action="<?= \App\Core\Controller::url('/m/purchase_stocks/update_consolidated_roll') ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="concern_id" id="edit_roll_concern_id">
                                <input type="hidden" name="source_color" id="edit_roll_source_color">
                                <input type="hidden" name="source_design_name" id="edit_roll_source_design">
                                <div class="modal-header py-2">
                                    <h5 class="modal-title fs-6 fw-bold" id="editConsolidatedRollModalLabel">Edit Consolidated Fabric Stock</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="edit_roll_design" class="form-label small fw-semibold">Design Name / Pattern</label>
                                            <input type="text" class="form-control form-control-sm" id="edit_roll_design" name="design_name" maxlength="150" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="edit_roll_color" class="form-label small fw-semibold">Color / Shade</label>
                                            <input type="text" class="form-control form-control-sm" id="edit_roll_color" name="color" maxlength="100" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="edit_roll_hsn" class="form-label small fw-semibold">HSN</label>
                                            <input type="text" class="form-control form-control-sm font-monospace" id="edit_roll_hsn" name="hsn_code" maxlength="50" data-hsn-autocomplete data-hsn-type="HSN" autocomplete="off">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="edit_roll_qty" class="form-label small fw-semibold">Accumulated Stock</label>
                                            <input type="number" class="form-control form-control-sm" id="edit_roll_qty" name="qty" min="0" max="9999999999.99" step="0.01" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="edit_roll_unit" class="form-label small fw-semibold">Unit</label>
                                            <input type="text" class="form-control form-control-sm" id="edit_roll_unit" name="unit" maxlength="50" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="edit_roll_weight" class="form-label small fw-semibold">Total Fabric Weight (kg)</label>
                                            <input type="number" class="form-control form-control-sm" id="edit_roll_weight" name="net_weight" min="0" max="9999999999.99" step="0.01" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="edit_roll_rate" class="form-label small fw-semibold">Unit Rate (₹)</label>
                                            <input type="number" class="form-control form-control-sm" id="edit_roll_rate" name="last_unit_price" min="0" max="9999999999.99" step="0.01" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="edit_roll_total" class="form-label small fw-semibold">Total Valuation (₹)</label>
                                            <input type="number" class="form-control form-control-sm" id="edit_roll_total" name="total_value" min="0" max="9999999999.99" step="0.01" required>
                                        </div>
                                    </div>
                                    <div class="alert alert-info py-2 mt-3 mb-0 small">
                                        These edits apply to the consolidated stock row only. Purchase bill history remains unchanged.
                                    </div>
                                </div>
                                <div class="modal-footer py-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- View B: Individual Roll Batches (Detailed Roll Breakdown) -->
            <div id="viewRollDetailed" class="card-body p-0" style="<?= $rollViewMode === 'consolidated' ? 'display: none;' : '' ?>">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0 text-center small compact-purchase-stock-table">
                        <thead class="table-light">
                            <tr class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                                <th style="width: 40px;">#</th>
                                <th style="width: 60px;">Photo</th>
                                <th style="width: 110px;">Roll Code</th>
                                <th class="text-start">Fabric / Pattern</th>
                                <th>Color / Shade</th>
                                <th>HSN</th>
                                <th>Weight (kg)</th>
                                <th>Inward Qty</th>
                                <th>Available Stock</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total (₹)</th>
                                <th class="text-start">Bill & Supplier</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 90px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rolls)): ?>
                                <tr>
                                    <td colspan="14" class="text-center py-5 text-muted">
                                        <i class="bi bi-stack display-6 text-secondary d-block mb-2"></i>
                                        <strong>No fabric rolls found in purchase stock.</strong>
                                        <div class="small mt-1">Record a purchase bill with fabric rolls and click <strong>Add to Stock</strong> in Label Generation to track roll inventory.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $rIdx = 1;
                                foreach ($rolls as $r): 
                                    $isInStock = ($r['inward_status'] ?? '') === 'completed';
                                    $wt = (float)($r['net_weight'] ?? 0);
                                    $qty = (float)($r['qty'] ?? 1);
                                    $availQty = isset($r['available_qty']) ? (float)$r['available_qty'] : $qty;
                                    $availWt = isset($r['available_weight']) ? (float)$r['available_weight'] : $wt;
                                    $allocQty = (float)($r['allocated_qty'] ?? 0);
                                    $dmgQty = (float)($r['damaged_qty'] ?? 0);
                                    $uPrice = (float)($r['unit_price'] ?? 0);
                                    $tot = (float)($r['total_price'] ?? 0);

                                    $cleanTitle = htmlspecialchars($r['item_name'] ?: 'Fabric Roll');
                                    if (($r['item_name'] === $r['item_code'] || is_numeric($r['item_name'])) && !empty($r['pattern'])) {
                                        $cleanTitle = htmlspecialchars(ucwords($r['pattern'])) . ' Fabric';
                                    }
                                ?>
                                    <tr>
                                        <td class="text-muted fw-semibold"><?= $rIdx++ ?></td>
                                        <td>
                                            <?php if (!empty($r['photo_path'])): ?>
                                                <a href="<?= htmlspecialchars($r['photo_path']) ?>" target="_blank" title="View Full Roll Sample">
                                                    <img src="<?= htmlspecialchars($r['photo_path']) ?>" alt="Roll Photo" class="rounded border shadow-sm" style="width: 36px; height: 36px; object-fit: cover;">
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border p-1.5"><i class="bi bi-image"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="font-monospace fw-bold text-dark badge bg-light text-dark border">
                                                <?= htmlspecialchars($r['item_code'] ?: ('#' . $r['id'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-start">
                                            <div class="fw-bold text-dark"><?= $cleanTitle ?></div>
                                            <?php if (!empty($r['pattern'])): ?>
                                                <div class="text-muted small" style="font-size: 0.72rem;">Pattern: <?= htmlspecialchars($r['pattern']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['color'])): ?>
                                                <span class="badge bg-light text-dark border fw-normal text-capitalize">
                                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.55rem; color: #4f46e5;"></i><?= htmlspecialchars($r['color']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="font-monospace small"><?= htmlspecialchars($r['hsn_code'] ?: '-') ?></td>
                                        <td class="fw-semibold text-secondary">
                                            <?= $wt > 0 ? number_format($wt, 2) . ' kg' : '-' ?>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?= (float)$availQty ?> <span class="fw-normal text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($r['unit'] ?: 'Rolls') ?></span>
                                            <?php if ($allocQty > 0 || $dmgQty > 0): ?>
                                                <div class="text-muted fw-normal" style="font-size: 0.68rem;">(Inward: <?= (float)$qty ?>)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isInStock): ?>
                                                <?php if ($availQty <= 0): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                                        <i class="bi bi-x-circle me-1"></i>0 Rolls (Utilized)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                        <i class="bi bi-check-circle me-1"></i><?= (float)$availQty ?> <?= htmlspecialchars($r['unit'] ?: 'Rolls') ?> (<?= number_format($availWt, 2) ?> kg)
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($allocQty > 0): ?>
                                                    <div class="text-primary small" style="font-size: 0.68rem;"><i class="bi bi-arrow-right-short"></i><?= (float)$allocQty ?> in program</div>
                                                <?php endif; ?>
                                                <?php if ($dmgQty > 0): ?>
                                                    <div class="text-danger small" style="font-size: 0.68rem;"><i class="bi bi-exclamation-triangle me-1"></i><?= (float)$dmgQty ?> damaged</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                                    <i class="bi bi-hourglass-split me-1"></i><?= (float)$qty ?> In Transit
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold text-dark">
                                            ₹<?= number_format($uPrice, 2) ?>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            ₹<?= number_format($tot, 2) ?>
                                        </td>
                                        <td class="text-start">
                                            <div>
                                                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/view?id=' . $r['purchase_bill_id']) ?>" class="fw-semibold text-primary font-monospace text-decoration-none" title="View Purchase Bill">
                                                    <?= htmlspecialchars($r['bill_no']) ?>
                                                </a>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.70rem;">
                                                <?= htmlspecialchars($r['supplier_name'] ?: 'Direct Supplier') ?> &bull; <?= date('d M Y', strtotime($r['bill_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($isInStock): ?>
                                                <span class="badge bg-success text-white px-2 py-1" style="font-size: 0.68rem;">IN STOCK</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-2 py-1" style="font-size: 0.68rem;">PENDING</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/view?id=' . $r['purchase_bill_id']) ?>" class="btn btn-outline-secondary btn-sm p-1" title="View Bill">
                                                    <i class="bi bi-receipt"></i>
                                                </a>
                                                <a href="<?= \App\Core\Controller::url('/m/label_generation?tab=purchase_raw&roll=' . urlencode($r['item_code'])) ?>" class="btn btn-outline-primary btn-sm p-1" title="Print QR Label">
                                                    <i class="bi bi-qr-code"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================== -->
        <!-- TAB SECTION 2: OTHER GARMENTS & TRIMS          -->
        <!-- ============================================== -->
        <?php if ($activeTab === 'garment_other'): ?>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0 text-center small compact-purchase-stock-table">
                        <thead class="table-light">
                            <tr class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                                <th style="width: 40px;">#</th>
                                <th style="width: 120px;">Item Code</th>
                                <th class="text-start">Supply Item Name & Description</th>
                                <th>Unit of Measure</th>
                                <th>Inwarded Qty</th>
                                <th>Available Stock</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Stock Value (₹)</th>
                                <th class="text-start">Purchase Bill Ref</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 90px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($garmentOther)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="bi bi-scissors display-6 text-secondary d-block mb-2"></i>
                                        <strong>No garment supplies or trims found in purchase stock.</strong>
                                        <div class="small mt-1">Record buttons, threads, zippers, or trims in purchase billing and inward them via <strong>Add to Stock</strong> in Label Generation.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $gIdx = 1;
                                foreach ($garmentOther as $g): 
                                    $isInStock = ($g['inward_status'] ?? '') === 'completed';
                                    $qty = (float)($g['qty'] ?? 1);
                                    $availableQty = (float)($g['available_qty'] ?? $qty);
                                    $uPrice = (float)($g['unit_price'] ?? 0);
                                    $tot = (float)($g['available_value'] ?? $g['total_price'] ?? 0);
                                    $labelDesign = trim($g['pattern'] ?: ($g['specification'] ?: 'STANDARD'));
                                    $labelItemName = $g['item_name'] ?: 'Garment Supply';
                                    $labelCode = \App\Services\QrService::generateTagCode(
                                        $g['item_name'] ?: 'Trims',
                                        $g['color'] ?? '',
                                        $labelDesign,
                                        (string)$g['id']
                                    );
                                    $labelPrice = '₹' . number_format($uPrice, 2);
                                    $labelConcern = $g['concern_name'] ?: 'GRM Inventory';
                                    $labelRef = $g['bill_no'] ?: ('PO-' . $g['purchase_bill_id']);
                                ?>
                                    <tr>
                                        <td class="text-muted fw-semibold"><?= $gIdx++ ?></td>
                                        <td>
                                            <span class="font-monospace fw-bold text-dark badge bg-light text-dark border">
                                                <?= htmlspecialchars($g['item_code'] ?: ('SUP-' . $g['id'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-start">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($g['item_name']) ?></div>
                                            <?php if (!empty($g['specification']) && $g['specification'] !== $g['item_name']): ?>
                                                <div class="text-muted small" style="font-size: 0.72rem;"><?= htmlspecialchars($g['specification']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($g['unit'] ?: 'Pcs') ?></span>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?= (float)$qty ?> <?= htmlspecialchars($g['unit'] ?: 'Pcs') ?>
                                        </td>
                                        <td>
                                            <?php if ($isInStock && $availableQty > 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-box-seam me-1"></i><?= number_format($availableQty, 2) ?> <?= htmlspecialchars($g['unit'] ?: 'Pcs') ?> Available
                                                </span>
                                            <?php elseif ($isInStock): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                    <i class="bi bi-shield-x me-1"></i>Quarantined / Out of Stock
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                                    <i class="bi bi-hourglass-split me-1"></i>Pending Inward
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold text-dark">
                                            ₹<?= number_format($uPrice, 2) ?>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            ₹<?= number_format($tot, 2) ?>
                                        </td>
                                        <td class="text-start">
                                            <div>
                                                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/view?id=' . $g['purchase_bill_id']) ?>" class="fw-semibold text-primary font-monospace text-decoration-none">
                                                    <?= htmlspecialchars($g['bill_no']) ?>
                                                </a>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.70rem;">
                                                <?= htmlspecialchars($g['supplier_name'] ?: 'Vendor') ?> &bull; <?= date('d M Y', strtotime($g['bill_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($isInStock && $availableQty > 0): ?>
                                                <span class="badge bg-success text-white px-2 py-1" style="font-size: 0.68rem;">IN STOCK</span>
                                            <?php elseif ($isInStock): ?>
                                                <span class="badge bg-danger text-white px-2 py-1" style="font-size: 0.68rem;">QUARANTINED</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-2 py-1" style="font-size: 0.68rem;">PENDING</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-outline-primary btn-sm p-1"
                                                    data-qr="<?= htmlspecialchars($labelCode, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-label-concern="<?= htmlspecialchars($labelConcern, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-label-ref="<?= htmlspecialchars($labelRef, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-label-title="<?= htmlspecialchars($labelItemName, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-label-field1-label="Item"
                                                    data-label-fabric="<?= htmlspecialchars($labelItemName, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-label-color="<?= htmlspecialchars($g['color'] ?: 'STANDARD', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-label-design="<?= htmlspecialchars($labelDesign, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-label-price="<?= htmlspecialchars($labelPrice, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-label-code="<?= htmlspecialchars($labelCode, ENT_QUOTES, 'UTF-8') ?>"
                                                    onclick="showRollQr(this)"
                                                    title="Preview the exact supply label">
                                                    <i class="bi bi-qr-code"></i>
                                                </button>
                                                <?php if ($canEditPurchaseStock): ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm p-1" title="Edit this stock item"
                                                        data-stock-type="garment_other"
                                                        data-item-id="<?= (int)$g['id'] ?>"
                                                        data-concern-id="<?= (int)$g['concern_id'] ?>"
                                                        data-item-name="<?= htmlspecialchars($g['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-item-code="<?= htmlspecialchars($g['item_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-specification="<?= htmlspecialchars($g['specification'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-color="<?= htmlspecialchars($g['color'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-unit="<?= htmlspecialchars($g['unit'] ?? 'Pieces', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-qty="<?= htmlspecialchars((string)$qty, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-unit-price="<?= htmlspecialchars((string)$uPrice, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-mrp="0"
                                                        data-total-value="<?= htmlspecialchars((string)$g['total_price'], ENT_QUOTES, 'UTF-8') ?>"
                                                        onclick="editPurchaseStockItem(this)">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================== -->
        <!-- TAB SECTION 3: PURCHASED FINISHED GOODS        -->
        <!-- ============================================== -->
        <?php if ($activeTab === 'finished_goods'): ?>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0 text-center small compact-purchase-stock-table">
                        <thead class="table-light">
                            <tr class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                                <th style="width: 40px;">#</th>
                                <th style="width: 130px;">Product Code</th>
                                <th class="text-start">Garment Product Name</th>
                                <th>Category / Type</th>
                                <th>Size / Spec</th>
                                <th>Color</th>
                                <th class="text-end">Purchase Cost</th>
                                <th class="text-end">MRP</th>
                                <th>Inward Qty</th>
                                <th>Available Stock</th>
                                <th class="text-end">Stock Value (₹)</th>
                                <th class="text-start">Purchase Bill Ref</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 90px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($finishedGoods)): ?>
                                <tr>
                                    <td colspan="14" class="text-center py-5 text-muted">
                                        <i class="bi bi-bag-check display-6 text-secondary d-block mb-2"></i>
                                        <strong>No finished goods found in purchase stock.</strong>
                                        <div class="small mt-1">Record finished garments in purchase billing and inward them via <strong>Add to Stock</strong> in Label Generation.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $fIdx = 1;
                                foreach ($finishedGoods as $f): 
                                    $isInStock = ($f['inward_status'] ?? '') === 'completed';
                                    $qty = (float)($f['qty'] ?? 1);
                                    $uPrice = (float)($f['unit_price'] ?? 0);
                                    $mrp = (float)($f['mrp'] ?? 0);
                                    $tot = (float)($f['total_price'] ?? 0);
                                    $liveStore = isset($f['live_variant_stock']) ? (float)$f['live_variant_stock'] : $qty;
                                    $labelProductName = $f['variant_name'] ?: ($f['item_name'] ?: 'Finished Garment');
                                    $labelDesign = trim($f['pattern'] ?: ($f['specification'] ?: ($f['variant_size'] ?: 'STANDARD')));
                                    $labelCode = !empty($f['variant_id'])
                                        ? \App\Services\QrService::formatFinishedGoodsCode(
                                            (int)$f['variant_id'],
                                            $f['main_category_code'] ?? null,
                                            $f['sub_category_code'] ?? null,
                                            $f['sub_sub_category_code'] ?? null,
                                            $f['category_name'] ?? null,
                                            $f['subcategory_name'] ?? null,
                                            $f['sub_subcategory_name'] ?? null
                                        )
                                        : '';
                                    $labelProductName = $f['fg_label_title'] ?: $labelProductName;
                                    $labelCodeDisplay = $labelCode;
                                    $labelPrice = (float)($f['variant_mrp'] ?: ($f['mrp'] ?: $f['unit_price']));
                                    $labelConcern = $f['concern_name'] ?: 'GRM Inventory';
                                    $labelRef = $f['bill_no'] ?: ('PO-' . $f['purchase_bill_id']);
                                ?>
                                    <tr>
                                        <td class="text-muted fw-semibold"><?= $fIdx++ ?></td>
                                        <td>
                                            <span class="font-monospace fw-bold text-dark badge bg-light text-dark border">
                                                <?= htmlspecialchars($f['variant_product_code'] ?: ($f['item_code'] ?: ('FG-' . $f['id']))) ?>
                                            </span>
                                        </td>
                                        <td class="text-start">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($f['item_name']) ?></div>
                                            <?php if (!empty($f['variant_name']) && $f['variant_name'] !== $f['item_name']): ?>
                                                <div class="text-muted small" style="font-size: 0.70rem;">Catalog: <?= htmlspecialchars($f['variant_name']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($f['category_name'] ?: 'Apparel') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border fw-semibold"><?= htmlspecialchars($f['specification'] ?: ($f['variant_size'] ?: 'Free')) ?></span>
                                        </td>
                                        <td>
                                            <?= !empty($f['color']) ? htmlspecialchars(ucwords($f['color'])) : '-' ?>
                                        </td>
                                        <td class="text-end fw-semibold text-dark">
                                            ₹<?= number_format($uPrice, 2) ?>
                                        </td>
                                        <td class="text-end fw-semibold text-primary">
                                            <?= $mrp > 0 ? ('₹' . number_format($mrp, 2)) : '-' ?>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?= (float)$qty ?> Pcs
                                        </td>
                                        <td>
                                            <?php if ($isInStock): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-box2-heart me-1"></i><?= $liveStore ?> Pcs in Store
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                                    <i class="bi bi-hourglass-split me-1"></i><?= (float)$qty ?> Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            ₹<?= number_format($tot, 2) ?>
                                        </td>
                                        <td class="text-start">
                                            <div>
                                                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/view?id=' . $f['purchase_bill_id']) ?>" class="fw-semibold text-primary font-monospace text-decoration-none">
                                                    <?= htmlspecialchars($f['bill_no']) ?>
                                                </a>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.70rem;">
                                                <?= htmlspecialchars($f['supplier_name'] ?: 'Vendor') ?> &bull; <?= date('d M Y', strtotime($f['bill_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($isInStock): ?>
                                                <span class="badge bg-success text-white px-2 py-1" style="font-size: 0.68rem;">IN STOCK</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-2 py-1" style="font-size: 0.68rem;">PENDING</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <?php if (!empty($f['label_generation_complete']) && !empty($f['variant_id'])): ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm p-1"
                                                        data-qr="<?= htmlspecialchars($labelCode, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label-concern="<?= htmlspecialchars($labelConcern, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label-ref="<?= htmlspecialchars($labelRef, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label-title="<?= htmlspecialchars($labelProductName, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label-field1-label="Product"
                                                        data-label-code-label="Product Code"
                                                        data-label-fabric="<?= htmlspecialchars($f['category_name'] ?: 'Apparel', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label-color="<?= htmlspecialchars($f['color'] ?: 'STANDARD', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label-design="<?= htmlspecialchars($labelDesign, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label-price="<?= htmlspecialchars('₹' . number_format($labelPrice, 2), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label-code="<?= htmlspecialchars($labelCodeDisplay, ENT_QUOTES, 'UTF-8') ?>"
                                                        onclick="showRollQr(this)"
                                                        title="Preview the generated finished-goods label">
                                                        <i class="bi bi-qr-code"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($canEditPurchaseStock): ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm p-1" title="Edit this stock item"
                                                        data-stock-type="finished_goods"
                                                        data-item-id="<?= (int)$f['id'] ?>"
                                                        data-concern-id="<?= (int)$f['concern_id'] ?>"
                                                        data-item-name="<?= htmlspecialchars($f['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-item-code="<?= htmlspecialchars($f['item_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-specification="<?= htmlspecialchars($f['specification'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-color="<?= htmlspecialchars($f['color'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-unit="<?= htmlspecialchars($f['unit'] ?? 'Pieces', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-qty="<?= htmlspecialchars((string)$qty, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-unit-price="<?= htmlspecialchars((string)$uPrice, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-mrp="<?= htmlspecialchars((string)$mrp, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-total-value="<?= htmlspecialchars((string)$tot, ENT_QUOTES, 'UTF-8') ?>"
                                                        onclick="editPurchaseStockItem(this)">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab !== 'rolls' && $canEditPurchaseStock): ?>
            <div class="modal fade" id="editPurchaseStockItemModal" tabindex="-1" aria-labelledby="editPurchaseStockItemModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <form action="<?= \App\Core\Controller::url('/m/purchase_stocks/update_item') ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="concern_id" id="edit_stock_concern_id">
                            <input type="hidden" name="item_id" id="edit_stock_item_id">
                            <input type="hidden" name="section_type" id="edit_stock_section_type">
                            <div class="modal-header py-2">
                                <h5 class="modal-title fs-6 fw-bold" id="editPurchaseStockItemModalLabel">Edit Purchase Stock Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="edit_stock_item_name" class="form-label small fw-semibold">Item Name</label>
                                        <input type="text" class="form-control form-control-sm" id="edit_stock_item_name" name="item_name" maxlength="255" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit_stock_item_code" class="form-label small fw-semibold">Item Code / SKU</label>
                                        <input type="text" class="form-control form-control-sm" id="edit_stock_item_code" name="item_code" maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit_stock_specification" class="form-label small fw-semibold">Specification / Size</label>
                                        <input type="text" class="form-control form-control-sm" id="edit_stock_specification" name="specification" maxlength="255">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit_stock_color" class="form-label small fw-semibold">Color</label>
                                        <input type="text" class="form-control form-control-sm" id="edit_stock_color" name="color" maxlength="100">
                                    </div>
                                    <div class="col-md-4" id="edit_stock_unit_group">
                                        <label for="edit_stock_unit" class="form-label small fw-semibold">Unit</label>
                                        <input type="text" class="form-control form-control-sm" id="edit_stock_unit" name="unit" maxlength="50" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="edit_stock_qty" class="form-label small fw-semibold">Inward Quantity</label>
                                        <input type="number" class="form-control form-control-sm" id="edit_stock_qty" name="qty" min="0" max="9999999999.99" step="0.01" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="edit_stock_unit_price" class="form-label small fw-semibold">Unit Price (₹)</label>
                                        <input type="number" class="form-control form-control-sm" id="edit_stock_unit_price" name="unit_price" min="0" max="9999999999.99" step="0.01" required>
                                    </div>
                                    <div class="col-md-4" id="edit_stock_mrp_group">
                                        <label for="edit_stock_mrp" class="form-label small fw-semibold">MRP (₹)</label>
                                        <input type="number" class="form-control form-control-sm" id="edit_stock_mrp" name="mrp" min="0" max="9999999999.99" step="0.01">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="edit_stock_total_value" class="form-label small fw-semibold">Stock Value (₹)</label>
                                        <input type="number" class="form-control form-control-sm" id="edit_stock_total_value" name="total_value" min="0" max="9999999999.99" step="0.01" required>
                                    </div>
                                </div>
                                <div class="alert alert-info py-2 mt-3 mb-0 small">
                                    These edits apply to this purchase stock row only. Purchase bill history remains unchanged.
                                    For finished goods, an edit hides the QR until labels are generated again.
                                </div>
                            </div>
                            <div class="modal-footer py-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer Pagination / Summary Info -->
        <div class="card-footer bg-white border-top p-3 d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
            <div>
                Showing <strong><?= count($activeTab === 'rolls' ? $consolidatedRolls : ($activeTab === 'garment_other' ? $garmentOther : $finishedGoods)) ?></strong> <?= $activeTab === 'rolls' ? 'consolidated specification(s)' : 'items' ?> in <strong><?= match($activeTab) { 'rolls' => 'Fabric Rolls', 'garment_other' => 'Other Garments & Trims', 'finished_goods' => 'Purchased Finished Goods', default => 'Stocks' } ?></strong>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span><i class="bi bi-shield-check text-success me-1"></i> Multi-tenant data isolated</span>
                <span><i class="bi bi-arrow-repeat text-primary me-1"></i> Live inventory synced</span>
            </div>
        </div>
    </div>
</div>

<?php if ($activeTab !== 'rolls'): ?>
    <style>
        #rollQrModal .label-sticker { width: 100%; max-width: 420px; min-height: 140px; padding: 8px 12px; border: 1.5px solid #0f172a; border-radius: 10px; background: #fff; color: #0f172a; display: flex; flex-direction: column; box-sizing: border-box; font-family: Arial, sans-serif; }
        #rollQrModal .label-header-bar { display: flex; align-items: center; justify-content: space-between; gap: 6px; padding-bottom: 4px; }
        #rollQrModal .concern-check-icon { width: 17px; height: 17px; margin-right: 6px; border-radius: 4px; background: #0d6efd; color: #fff; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 11px; }
        #rollQrModal .concern-name-text { max-width: 170px; overflow: hidden; color: #0f172a; font-size: .82rem; font-weight: 800; letter-spacing: .04em; text-overflow: ellipsis; text-transform: uppercase; white-space: nowrap; }
        #rollQrModal .ref-pill-badge { padding: 1px 7px; border: 1px solid #bae6fd; border-radius: 12px; background: #e0f2fe; color: #0284c7; font: 600 .7rem monospace; white-space: nowrap; }
        #rollQrModal .label-divider { margin: 4px 0 6px; border-top: 1px dashed #cbd5e1; }
        #rollQrModal .label-body-content { display: flex; flex: 1; align-items: center; gap: 10px; }
        #rollQrModal .label-qr-wrapper, #rollQrModal .label-qr-box { width: 76px; height: 76px; flex-shrink: 0; }
        #rollQrModal .label-qr-wrapper { display: flex; align-items: center; justify-content: center; background: #fff; }
        #rollQrModal .label-details-stack { flex: 1; overflow: hidden; font-size: .76rem; line-height: 1.35; }
        #rollQrModal .label-item-title { margin-bottom: 2px; overflow: hidden; color: #0f172a; font-size: .86rem; font-weight: 800; text-overflow: ellipsis; text-transform: uppercase; white-space: nowrap; }
        #rollQrModal .label-detail-row { overflow: hidden; color: #334155; font-size: .75rem; text-overflow: ellipsis; white-space: nowrap; }
        #rollQrModal .detail-label { margin-right: 3px; color: #64748b; font-weight: 500; }
        #rollQrModal .detail-val { color: #0f172a; font-weight: 600; }
    </style>
<?php endif; ?>
<div class="modal fade" id="rollQrModal" tabindex="-1" aria-labelledby="rollQrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6 fw-bold" id="rollQrModalLabel">Purchase Stock Label Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body d-flex justify-content-center">
                <div class="label-sticker">
                    <div class="label-header-bar">
                        <div class="d-flex align-items-center">
                            <span class="concern-check-icon"><i class="bi bi-check-lg"></i></span>
                            <span class="concern-name-text" id="rollQrConcern"></span>
                        </div>
                        <span class="ref-pill-badge" id="rollQrRef"></span>
                    </div>
                    <div class="label-divider"></div>
                    <div class="label-body-content">
                        <div class="label-qr-wrapper"><div class="label-qr-box" id="rollQrCode"></div></div>
                        <div class="label-details-stack">
                            <div class="label-item-title" id="rollQrTitle"></div>
                            <div class="label-detail-row"><span class="detail-label" id="rollQrField1Label">Fabric:</span> <span class="detail-val" id="rollQrFabric"></span></div>
                            <div class="label-detail-row"><span class="detail-label">Color:</span> <span class="detail-val text-uppercase" id="rollQrColor"></span></div>
                            <div class="label-detail-row"><span class="detail-label">Design:</span> <span class="detail-val text-uppercase" id="rollQrDesign"></span></div>
                            <div class="label-detail-row"><span class="detail-label">Price:</span> <span class="detail-val fw-bold text-success" id="rollQrPrice"></span></div>
                            <div class="label-detail-row"><span class="detail-label" id="rollQrCodeLabel">Code:</span> <span class="detail-val font-monospace text-primary fw-bold" id="rollQrCodeText"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showRollQr(button) {
    const qrContainer = document.getElementById('rollQrCode');
    const modalElement = document.getElementById('rollQrModal');
    if (!qrContainer || !modalElement || !button) return;
    if (typeof QRCode === 'undefined') {
        alert('QR display is unavailable. Refresh the page and try again.');
        return;
    }

    const payload = button.dataset.qr || '';
    document.getElementById('rollQrConcern').textContent = button.dataset.labelConcern || 'GRM Inventory';
    document.getElementById('rollQrRef').textContent = button.dataset.labelRef || '';
    document.getElementById('rollQrTitle').textContent = button.dataset.labelTitle || '';
    document.getElementById('rollQrField1Label').textContent = (button.dataset.labelField1Label || 'Fabric') + ':';
    document.getElementById('rollQrFabric').textContent = button.dataset.labelFabric || '';
    document.getElementById('rollQrColor').textContent = button.dataset.labelColor || '';
    document.getElementById('rollQrDesign').textContent = button.dataset.labelDesign || '';
    document.getElementById('rollQrPrice').textContent = button.dataset.labelPrice || '₹0.00';
    document.getElementById('rollQrCodeLabel').textContent = (button.dataset.labelCodeLabel || 'Code') + ':';
    document.getElementById('rollQrCodeText').textContent = button.dataset.labelCode || payload;
    qrContainer.replaceChildren();
    new QRCode(qrContainer, {
        text: payload,
        width: 80,
        height: 80,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
}

function editConsolidatedRoll(button) {
    const modalElement = document.getElementById('editConsolidatedRollModal');
    if (!modalElement || !button) return;

    document.getElementById('edit_roll_concern_id').value = button.dataset.concernId || '';
    document.getElementById('edit_roll_source_color').value = button.dataset.sourceColor || '';
    document.getElementById('edit_roll_source_design').value = button.dataset.sourceDesign || '';
    document.getElementById('edit_roll_color').value = button.dataset.color || '';
    document.getElementById('edit_roll_design').value = button.dataset.design || '';
    document.getElementById('edit_roll_hsn').value = button.dataset.hsn || '';
    document.getElementById('edit_roll_qty').value = button.dataset.qty || '0';
    document.getElementById('edit_roll_weight').value = button.dataset.weight || '0';
    document.getElementById('edit_roll_unit').value = button.dataset.unit || 'Rolls';
    document.getElementById('edit_roll_rate').value = button.dataset.rate || '0';
    document.getElementById('edit_roll_total').value = button.dataset.total || '0';

    bindRollAutoCalc();

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
}

let rollCalcBound = false;
function bindRollAutoCalc() {
    if (rollCalcBound) return;
    rollCalcBound = true;

    const qtyInput = document.getElementById('edit_roll_qty');
    const rateInput = document.getElementById('edit_roll_rate');
    const totalInput = document.getElementById('edit_roll_total');

    if (!qtyInput || !rateInput || !totalInput) return;

    function recalcTotalFromRate() {
        const qty = parseFloat(qtyInput.value) || 0;
        const rate = parseFloat(rateInput.value) || 0;
        totalInput.value = (qty * rate).toFixed(2);
    }

    function recalcRateFromTotal() {
        const qty = parseFloat(qtyInput.value) || 0;
        const total = parseFloat(totalInput.value) || 0;
        if (qty > 0) {
            rateInput.value = (total / qty).toFixed(2);
        }
    }

    qtyInput.addEventListener('input', recalcTotalFromRate);
    rateInput.addEventListener('input', recalcTotalFromRate);
    totalInput.addEventListener('input', recalcRateFromTotal);
}

function editPurchaseStockItem(button) {
    const modalElement = document.getElementById('editPurchaseStockItemModal');
    if (!modalElement || !button) return;

    const type = button.dataset.stockType || '';
    document.getElementById('edit_stock_concern_id').value = button.dataset.concernId || '';
    document.getElementById('edit_stock_item_id').value = button.dataset.itemId || '';
    document.getElementById('edit_stock_section_type').value = type;
    document.getElementById('edit_stock_item_name').value = button.dataset.itemName || '';
    document.getElementById('edit_stock_item_code').value = button.dataset.itemCode || '';
    document.getElementById('edit_stock_specification').value = button.dataset.specification || '';
    document.getElementById('edit_stock_color').value = button.dataset.color || '';
    document.getElementById('edit_stock_unit').value = button.dataset.unit || 'Pieces';
    document.getElementById('edit_stock_qty').value = button.dataset.qty || '0';
    document.getElementById('edit_stock_unit_price').value = button.dataset.unitPrice || '0';
    document.getElementById('edit_stock_mrp').value = button.dataset.mrp || '0';
    document.getElementById('edit_stock_total_value').value = button.dataset.totalValue || '0';
    document.getElementById('edit_stock_mrp_group').classList.toggle('d-none', type !== 'finished_goods');
    document.getElementById('editPurchaseStockItemModalLabel').textContent =
        type === 'finished_goods' ? 'Edit Finished Goods Stock' : 'Edit Garment Supply / Trim';

    bindItemAutoCalc();

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
}

let itemCalcBound = false;
function bindItemAutoCalc() {
    if (itemCalcBound) return;
    itemCalcBound = true;

    const qtyInput = document.getElementById('edit_stock_qty');
    const priceInput = document.getElementById('edit_stock_unit_price');
    const totalInput = document.getElementById('edit_stock_total_value');

    if (!qtyInput || !priceInput || !totalInput) return;

    function recalcTotalFromPrice() {
        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        totalInput.value = (qty * price).toFixed(2);
    }

    function recalcPriceFromTotal() {
        const qty = parseFloat(qtyInput.value) || 0;
        const total = parseFloat(totalInput.value) || 0;
        if (qty > 0) {
            priceInput.value = (total / qty).toFixed(2);
        }
    }

    qtyInput.addEventListener('input', recalcTotalFromPrice);
    priceInput.addEventListener('input', recalcTotalFromPrice);
    totalInput.addEventListener('input', recalcPriceFromTotal);
}

function switchRollView(mode) {
    const consView = document.getElementById('viewRollConsolidated');
    const detView = document.getElementById('viewRollDetailed');
    const btnCons = document.getElementById('btnRollConsolidated');
    const btnDet = document.getElementById('btnRollDetailed');

    if (!consView || !detView) return;

    if (mode === 'detailed') {
        consView.style.display = 'none';
        detView.style.display = 'block';
        if (btnCons) {
            btnCons.className = 'btn btn-outline-secondary bg-white fw-semibold';
        }
        if (btnDet) {
            btnDet.className = 'btn btn-primary fw-semibold';
        }
    } else {
        consView.style.display = 'block';
        detView.style.display = 'none';
        if (btnCons) {
            btnCons.className = 'btn btn-primary fw-semibold';
        }
        if (btnDet) {
            btnDet.className = 'btn btn-outline-secondary bg-white fw-semibold';
        }
    }
}
</script>
