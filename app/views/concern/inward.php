<?php
// app/views/concern/inward.php - Production Inward (GRN) Management
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs & Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
                    <li class="breadcrumb-item"><span class="text-muted">Warehouse</span></li>
                    <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">Inward / GRN</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-in-down text-primary"></i> Production Inward
                <?php if (!empty($currentConcern)): ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 fw-semibold ms-2">
                        <i class="bi bi-building me-1"></i> <?= htmlspecialchars($currentConcern['name']) ?> (<?= htmlspecialchars($currentConcern['code']) ?>)
                    </span>
                <?php elseif (!empty($selectedCid)): ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fs-6 fw-semibold ms-2">
                        <i class="bi bi-building me-1"></i> Concern #<?= (int)$selectedCid ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fs-6 fw-semibold ms-2">
                        <i class="bi bi-globe me-1"></i> Consolidated (All Concerns)
                    </span>
                <?php endif; ?>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- 1. Top 3 KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Awaiting GRN -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3" style="border-left: 4px solid #ef4444 !important; background: #fff;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted" style="letter-spacing: 0.05em; font-size: 0.72rem;">AWAITING GRN (MISSING STOCK)</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?= (int)$awaitingBatchesCount ?> Batches</div>
                        <div class="small text-danger mt-1 fw-medium">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= number_format($pendingPcsSum) ?> pcs pending verification
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: #fee2e2; color: #ef4444;">
                        <i class="bi bi-hourglass-split fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Received & Stock Credited -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3" style="border-left: 4px solid #10b981 !important; background: #fff;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted" style="letter-spacing: 0.05em; font-size: 0.72rem;">RECEIVED & STOCK CREDITED</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?= (int)$receivedBatchesCount ?> Batches</div>
                        <div class="small text-success mt-1 fw-medium">
                            <i class="bi bi-shield-check me-1"></i> <?= number_format($warehouseStockPcsSum) ?> pcs in warehouse stock
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: #d1fae5; color: #10b981;">
                        <i class="bi bi-shield-check fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Inward Dispatches -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3" style="border-left: 4px solid #3b82f6 !important; background: #fff;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted" style="letter-spacing: 0.05em; font-size: 0.72rem;">TOTAL INWARD DISPATCHES</div>
                        <div class="fs-3 fw-bold text-dark mt-1"><?= (int)$totalDispatchesCount ?> Batches</div>
                        <div class="small text-muted mt-1 fw-medium">
                            <i class="bi bi-box-seam me-1"></i> <?= number_format($totalDispatchedPcsSum) ?> total dispatched pcs
                        </div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: #eff6ff; color: #3b82f6;">
                        <i class="bi bi-box-seam fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Toolbar Card -->
    <?php
    $exportTitle = 'Export Production Inward Consignments (Excel, PDF, CSV)';
    $exportSubtitle = 'Download incoming outward shipments, warehouse stock credits & verified GRN pieces';
    $exportUrl = \App\Core\Controller::url('/m/inward/export');
    $currentModule = 'inward';
    $prefix = 'inw_exp';
    include dirname(__DIR__) . '/partials/export_card.php';
    ?>

    <!-- 2. View Mode Switcher -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="small text-muted fw-bold text-uppercase" style="font-size: 0.75rem;">VIEW MODE:</span>
        <button type="button" class="btn btn-sm btn-primary px-3 rounded-pill fw-semibold shadow-sm view-mode-btn active" id="btnModeLedger" onclick="switchViewMode('ledger')">
            <i class="bi bi-list-task me-1"></i> List / Ledger View <span class="badge bg-white text-primary ms-1"><?= count($inwardBatches) ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary px-3 rounded-pill fw-semibold view-mode-btn" id="btnModeCards" onclick="switchViewMode('cards')">
            <i class="bi bi-grid-fill me-1"></i> Batch Cards View <span class="badge bg-secondary ms-1"><?= count($inwardBatches) ?></span>
        </button>
    </div>

    <!-- 3. Search & Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 p-3 bg-white">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1 fw-semibold">Search Filter</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control form-control-sm border-start-0" id="filterSearch" placeholder="Search batch, unit, vehicle..." onkeyup="filterBatches()">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1 fw-semibold">Source Production Unit</label>
                <select class="form-select form-select-sm" id="filterUnit" onchange="filterBatches()">
                    <option value="">All Production Units</option>
                    <?php foreach ($productionUnits as $pu): ?>
                        <option value="<?= htmlspecialchars($pu['name']) ?>"><?= htmlspecialchars($pu['name']) ?> (<?= htmlspecialchars($pu['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1 fw-semibold">Destination Warehouse</label>
                <select class="form-select form-select-sm" id="filterWarehouse" onchange="filterBatches()">
                    <option value="">All Destination Facilities</option>
                    <?php foreach ($allConcerns as $c): ?>
                        <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1 fw-semibold">Inward Status</label>
                <select class="form-select form-select-sm" id="filterStatus" onchange="filterBatches()">
                    <option value="">All Statuses</option>
                    <option value="partial">Partial Short</option>
                    <option value="awaiting">Awaiting Scan</option>
                    <option value="completed">Completed / Credited</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="button" class="btn btn-sm btn-primary w-100" onclick="filterBatches()" title="Filter"><i class="bi bi-funnel-fill"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="resetFilters()" title="Reset Filters"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
    </div>

    <!-- 4. VIEW A: Detailed Ledger Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white mb-4" id="viewLedgerContainer">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-table text-primary"></i> All Inward Batches Detailed Ledger
            </h6>
            <span class="badge bg-light text-dark border font-monospace" id="dispatchesCounter">Showing <?= count($inwardBatches) ?> Dispatches</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="inwardLedgerTable">
                <thead class="table-light small text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                    <tr>
                        <th class="ps-3">BATCH</th>
                        <th>PROGRAM & PRODUCT</th>
                        <th>SOURCE UNIT → DESTINATION</th>
                        <th>QUANTITY & BOXES</th>
                        <th>DISPATCH DATE & VEHICLE</th>
                        <th>STATUS</th>
                        <th class="text-end pe-3">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inwardBatches)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                <div class="fw-bold fs-6 text-dark mb-1">No Incoming Outward Dispatches For This Concern</div>
                                <div class="small">Only outward consignments specifically dispatched to this destination concern appear here. Use <strong>Receive Inward Consignment</strong> below to record direct vendor receipts.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inwardBatches as $ib): 
                            $scanQty = $ib['short_qty'] > 0 ? (int)$ib['short_qty'] : (int)$ib['total_qty'];
                            $scanUrl = \App\Core\Controller::url('/m/inward/scan/' . $ib['outward_id']);
                        ?>
                        <tr class="inward-batch-row" 
                            data-search="<?= htmlspecialchars(strtolower($ib['batch_no'] . ' ' . $ib['outward_no'] . ' ' . $ib['program_name'] . ' ' . $ib['source_unit_name'] . ' ' . $ib['vehicle_number'])) ?>"
                            data-unit="<?= htmlspecialchars($ib['source_unit_name']) ?>"
                            data-warehouse="<?= htmlspecialchars($ib['dest_warehouse_name']) ?>"
                            data-status="<?= htmlspecialchars($ib['status_badge']) ?>">
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-qr-code text-primary fs-5"></i>
                                    <div>
                                        <a href="<?= $scanUrl ?>" class="fw-bold text-decoration-none text-primary font-monospace"><?= htmlspecialchars($ib['batch_no']) ?></a>
                                        <div>
                                            <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;"><i class="bi bi-send me-1"></i><?= htmlspecialchars($ib['outward_no']) ?></span>
                                            <?php if (!empty($ib['cutting_id'])): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.68rem;">CUTTING OUTWARD</span>
                                            <?php elseif (!empty($ib['damage_transfer'])): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.68rem;">DAMAGED STOCK</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($ib['program_name'] ?: 'KIDS - BABY - DRESS') ?></div>
                                <small class="text-muted font-monospace" style="font-size: 0.75rem;">Prog: <?= htmlspecialchars($ib['program_no']) ?></small>
                            </td>
                            <td>
                                <div class="small mb-1">
                                    <span class="text-muted">From:</span> <span class="fw-semibold text-dark"><?= htmlspecialchars($ib['source_unit_name']) ?></span>
                                    <span class="badge bg-warning bg-opacity-25 text-dark border border-warning font-monospace ms-1"><?= htmlspecialchars($ib['source_unit_code']) ?></span>
                                </div>
                                <div class="small">
                                    <span class="text-muted">To:</span> <span class="fw-semibold text-dark"><?= htmlspecialchars($ib['dest_warehouse_name']) ?></span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary font-monospace ms-1"><?= htmlspecialchars($ib['dest_warehouse_code']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="fs-6 fw-bold text-success">+<?= number_format($ib['total_qty']) ?> pcs</div>
                                <small class="text-muted"><?= (int)$ib['total_boxes'] ?> Cartons</small>
                            </td>
                            <td>
                                <div class="small text-dark fw-medium"><?= date('d M Y, h:i A', strtotime($ib['created_at'] ?: $ib['outward_date'])) ?></div>
                                <small class="text-muted"><i class="bi bi-truck me-1"></i><?= htmlspecialchars($ib['vehicle_number'] ?: 'rtt31232') ?></small>
                            </td>
                            <td>
                                <?php if ($ib['status_badge'] === 'partial'): ?>
                                    <span class="badge bg-warning text-dark border border-warning px-2.5 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Partial (<?= (int)$ib['short_qty'] ?> Short)
                                    </span>
                                <?php elseif ($ib['status_badge'] === 'awaiting'): ?>
                                    <span class="badge bg-info text-dark border border-info px-2.5 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                                        <i class="bi bi-hourglass-split me-1"></i> Awaiting Scan (<?= (int)$ib['short_qty'] ?> Pending)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success text-white border border-success px-2.5 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                                        <i class="bi bi-check-circle-fill me-1"></i> Received & Credited
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <?php if (empty($ib['damage_transfer']) && empty($ib['cutting_id']) && !empty($ib['batch_id'])): ?>
                                        <a href="<?= \App\Core\Controller::url('/production/batches/label/' . (int)$ib['batch_id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Batch Labels"><i class="bi bi-printer text-primary"></i></a>
                                    <?php endif; ?>
                                    <a href="<?= $scanUrl ?>" class="btn btn-sm btn-outline-primary" title="View Batch Details"><i class="bi bi-eye"></i></a>
                                    <a href="<?= $scanUrl ?>" class="btn btn-sm btn-outline-info" title="Consignment Log"><i class="bi bi-clock-history"></i></a>
                                    <?php if (\App\Core\Auth::can('inward', 'edit')): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Edit Consignment Notes"><i class="bi bi-pencil-square"></i></button>
                                    <?php endif; ?>
                                    <?php if (\App\Core\Auth::can('inward', 'delete')): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Consignment"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                    <?php if (!empty($ib['cutting_id'])): ?>
                                        <?php if ($ib['status_badge'] === 'completed'): ?>
                                            <a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="btn btn-sm btn-success fw-bold px-3 shadow-sm d-flex align-items-center gap-1">
                                                <i class="bi bi-scissors"></i> View in Cutting
                                            </a>
                                        <?php elseif (\App\Core\Auth::can('inward', 'edit') || \App\Core\Auth::can('inward', 'add')): ?>
                                            <form action="<?= \App\Core\Controller::url('/m/inward/receive-cutting') ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
                                                <input type="hidden" name="outward_id" value="<?= (int)$ib['outward_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success fw-bold px-3 shadow-sm d-flex align-items-center gap-1">
                                                    <i class="bi bi-box-arrow-in-down"></i> Add to Cutting (<?= $scanQty ?>)
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php elseif (\App\Core\Auth::can('inward', 'edit') || \App\Core\Auth::can('inward', 'add')): ?>
                                    <a href="<?= $scanUrl ?>" class="btn btn-sm btn-warning text-dark fw-bold px-3 shadow-sm d-flex align-items-center gap-1">
                                        <i class="bi bi-box-arrow-in-down"></i> <?= !empty($ib['damage_transfer']) ? 'Receive Damage' : 'Inward' ?> (<?= $scanQty ?>)
                                    </a>
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

    <!-- 5. VIEW B: Batch Cards View (Tablet / Mobile friendly) -->
    <div class="row g-3 d-none mb-4" id="viewCardsContainer">
        <?php if (empty($inwardBatches)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-3 p-5 text-center text-muted bg-white">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    <div class="fw-bold fs-6 text-dark mb-1">No Incoming Outward Dispatches For This Concern</div>
                    <div class="small">Only outward consignments specifically dispatched to this destination concern appear here.</div>
                </div>
            </div>
        <?php else: ?>
        <?php foreach ($inwardBatches as $ib): 
            $scanQty = $ib['short_qty'] > 0 ? (int)$ib['short_qty'] : (int)$ib['total_qty'];
            $scanUrl = \App\Core\Controller::url('/m/inward/scan/' . $ib['outward_id']);
        ?>
        <div class="col-12 col-md-6 col-xl-4 inward-card-item"
             data-search="<?= htmlspecialchars(strtolower($ib['batch_no'] . ' ' . $ib['outward_no'] . ' ' . $ib['program_name'] . ' ' . $ib['source_unit_name'] . ' ' . $ib['vehicle_number'])) ?>"
             data-unit="<?= htmlspecialchars($ib['source_unit_name']) ?>"
             data-warehouse="<?= htmlspecialchars($ib['dest_warehouse_name']) ?>"
             data-status="<?= htmlspecialchars($ib['status_badge']) ?>">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <a href="<?= $scanUrl ?>" class="fw-bold fs-6 text-primary font-monospace text-decoration-none"><?= htmlspecialchars($ib['batch_no']) ?></a>
                        <div><span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($ib['outward_no']) ?></span></div>
                        <?php if (!empty($ib['cutting_id'])): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1">CUTTING OUTWARD</span>
                        <?php elseif (!empty($ib['damage_transfer'])): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1">DAMAGED STOCK</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($ib['status_badge'] === 'partial'): ?>
                        <span class="badge bg-warning text-dark border border-warning px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Partial (<?= (int)$ib['short_qty'] ?> Short)</span>
                    <?php elseif ($ib['status_badge'] === 'awaiting'): ?>
                        <span class="badge bg-info text-dark border border-info px-2 py-1"><i class="bi bi-hourglass-split me-1"></i> Awaiting Scan</span>
                    <?php else: ?>
                        <span class="badge bg-success text-white border border-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Credited</span>
                    <?php endif; ?>
                </div>

                <div class="fw-semibold text-dark mb-1"><?= htmlspecialchars($ib['program_name'] ?: 'KIDS - BABY - DRESS') ?></div>
                <div class="small text-muted mb-2 font-monospace">Prog: <?= htmlspecialchars($ib['program_no']) ?></div>

                <div class="bg-light p-2 rounded-2 mb-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Origin Unit:</span>
                        <span class="fw-medium text-dark"><?= htmlspecialchars($ib['source_unit_name']) ?> [<?= htmlspecialchars($ib['source_unit_code']) ?>]</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Destination:</span>
                        <span class="fw-medium text-dark"><?= htmlspecialchars($ib['dest_warehouse_name']) ?> [<?= htmlspecialchars($ib['dest_warehouse_code']) ?>]</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Vehicle:</span>
                        <span class="font-monospace text-dark"><i class="bi bi-truck me-1"></i><?= htmlspecialchars($ib['vehicle_number'] ?: 'rtt31232') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Cartons:</span>
                        <span class="fw-bold text-dark"><?= (int)$ib['total_boxes'] ?> Cartons (+<?= number_format($ib['total_qty']) ?> pcs)</span>
                    </div>
                </div>

                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                    <small class="text-muted font-monospace"><?= date('d M Y, h:i A', strtotime($ib['created_at'] ?: $ib['outward_date'])) ?></small>
                    <div class="d-flex align-items-center gap-1">
                        <?php if (empty($ib['damage_transfer']) && empty($ib['cutting_id']) && !empty($ib['batch_id'])): ?>
                            <a href="<?= \App\Core\Controller::url('/production/batches/label/' . (int)$ib['batch_id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Batch Labels"><i class="bi bi-printer text-primary"></i></a>
                        <?php endif; ?>
                        <a href="<?= $scanUrl ?>" class="btn btn-sm btn-outline-primary" title="View Batch Details"><i class="bi bi-eye"></i></a>
                        <?php if (!empty($ib['cutting_id'])): ?>
                            <?php if ($ib['status_badge'] === 'completed'): ?>
                                <a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="btn btn-sm btn-success fw-bold px-3 shadow-sm d-flex align-items-center gap-1">
                                    <i class="bi bi-scissors"></i> View in Cutting
                                </a>
                            <?php elseif (\App\Core\Auth::can('inward', 'edit') || \App\Core\Auth::can('inward', 'add')): ?>
                                <form action="<?= \App\Core\Controller::url('/m/inward/receive-cutting') ?>" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
                                    <input type="hidden" name="outward_id" value="<?= (int)$ib['outward_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3 shadow-sm d-flex align-items-center gap-1">
                                        <i class="bi bi-box-arrow-in-down"></i> Add to Cutting (<?= $scanQty ?>)
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php elseif (\App\Core\Auth::can('inward', 'edit') || \App\Core\Auth::can('inward', 'add')): ?>
                        <a href="<?= $scanUrl ?>" class="btn btn-sm btn-warning text-dark fw-bold px-3 shadow-sm d-flex align-items-center gap-1">
                            <i class="bi bi-box-arrow-in-down"></i> <?= !empty($ib['damage_transfer']) ? 'Receive Damage' : 'Inward' ?> (<?= $scanQty ?>)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Dynamic Filter and View Mode Switcher Scripts -->
<script>
function switchViewMode(mode) {
    const ledger = document.getElementById('viewLedgerContainer');
    const cards = document.getElementById('viewCardsContainer');
    const btnLedger = document.getElementById('btnModeLedger');
    const btnCards = document.getElementById('btnModeCards');

    if (mode === 'cards') {
        ledger.classList.add('d-none');
        cards.classList.remove('d-none');
        btnCards.classList.add('btn-primary', 'shadow-sm', 'active');
        btnCards.classList.remove('btn-outline-secondary');
        btnLedger.classList.remove('btn-primary', 'shadow-sm', 'active');
        btnLedger.classList.add('btn-outline-secondary');
    } else {
        cards.classList.add('d-none');
        ledger.classList.remove('d-none');
        btnLedger.classList.add('btn-primary', 'shadow-sm', 'active');
        btnLedger.classList.remove('btn-outline-secondary');
        btnCards.classList.remove('btn-primary', 'shadow-sm', 'active');
        btnCards.classList.add('btn-outline-secondary');
    }
}

function filterBatches() {
    const q = (document.getElementById('filterSearch').value || '').toLowerCase().trim();
    const unit = (document.getElementById('filterUnit').value || '').trim();
    const warehouse = (document.getElementById('filterWarehouse').value || '').trim();
    const status = (document.getElementById('filterStatus').value || '').trim();

    // Table rows
    const rows = document.querySelectorAll('.inward-batch-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search') || '';
        const rowUnit = row.getAttribute('data-unit') || '';
        const rowStatus = row.getAttribute('data-status') || '';

        const matchQ = !q || rowSearch.includes(q);
        const matchUnit = !unit || rowUnit.includes(unit);
        const matchStatus = !status || rowStatus === status;

        if (matchQ && matchUnit && matchStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Cards
    const cards = document.querySelectorAll('.inward-card-item');
    cards.forEach(card => {
        const cSearch = card.getAttribute('data-search') || '';
        const cUnit = card.getAttribute('data-unit') || '';
        const cStatus = card.getAttribute('data-status') || '';

        const matchQ = !q || cSearch.includes(q);
        const matchUnit = !unit || cUnit.includes(unit);
        const matchStatus = !status || cStatus === status;

        if (matchQ && matchUnit && matchStatus) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    const counter = document.getElementById('dispatchesCounter');
    if (counter) {
        counter.textContent = `Showing ${visibleCount} Dispatches`;
    }
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterUnit').value = '';
    document.getElementById('filterWarehouse').value = '';
    document.getElementById('filterStatus').value = '';
    filterBatches();
}
</script>
