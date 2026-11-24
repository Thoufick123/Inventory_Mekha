<?php
// app/views/concern/inward_scan.php - Live Cross-Check, QR Scanning & Inward Reconcile
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs & Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
                    <li class="breadcrumb-item"><span class="text-muted">Warehouse</span></li>
                    <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/m/inward') ?>" class="text-decoration-none text-muted">Inward / GRN</a></li>
                    <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">Cross-Check Batch #<?= htmlspecialchars($outward['batch_no']) ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-in-down text-primary"></i> Complete Inward Shipment <span class="text-muted fs-6 font-monospace">#<?= htmlspecialchars($outward['batch_no']) ?></span>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= \App\Core\Controller::url('/m/inward') ?>" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- 1. Top 5 KPI Metric Cards -->
    <div class="row g-2 mb-4">
        <!-- 1. Outward Dispatched -->
        <div class="col-12 col-sm-6 col-lg">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white" style="border-left: 4px solid #3b82f6 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">OUTWARD DISPATCHED</div>
                        <div class="fs-4 fw-bold text-dark mt-1" id="statDispatched"><?= number_format($totalDispatched) ?> pcs</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #eff6ff; color: #3b82f6;">
                        <i class="bi bi-truck fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Inward Verified (Intact) -->
        <div class="col-12 col-sm-6 col-lg">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white" style="border-left: 4px solid #10b981 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">INWARD VERIFIED (INTACT)</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><span id="statVerified"><?= number_format($totalCredited) ?></span> pcs</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #d1fae5; color: #10b981;">
                        <i class="bi bi-qr-code-scan fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Damaged / Inactive -->
        <div class="col-12 col-sm-6 col-lg">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white" style="border-left: 4px solid #ef4444 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">DAMAGED / INACTIVE</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><span id="statDamaged"><?= number_format($totalDamaged) ?></span> pcs</div>
                        <div class="small text-danger fw-medium" style="font-size: 0.75rem;"><span id="statQuarantine"><?= number_format($totalDamaged) ?></span> quarantined units</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #fee2e2; color: #ef4444;">
                        <i class="bi bi-slash-circle fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Pending / Missing -->
        <div class="col-12 col-sm-6 col-lg">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white" style="border-left: 4px solid #f59e0b !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">PENDING / MISSING</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><span id="statPending"><?= number_format($pendingMissing) ?></span> pcs</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #fef3c7; color: #f59e0b;">
                        <i class="bi bi-exclamation-triangle fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Verification Match % -->
        <div class="col-12 col-sm-6 col-lg">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 bg-white" style="border-left: 4px solid #06b6d4 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">VERIFICATION MATCH</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><span id="statMatch"><?= $verificationMatch ?></span>%</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #cffafe; color: #0891b2;">
                        <i class="bi bi-check2-circle fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Main Content Split: Left (Batch QR & Details) | Right (Scanner & Reconcile Table) -->
    <div class="row g-3 mb-4">
        <!-- Left Column: Batch Carton QR & Outward Dispatch Details -->
        <div class="col-12 col-lg-4">
            <!-- Batch Carton QR Code -->
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3 text-center">
                <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.75rem;">
                    <i class="bi bi-qr-code me-1 text-primary"></i> Batch Carton QR Code
                </div>
                <div class="d-flex justify-content-center py-2">
                    <div id="batchCartonQrContainer" class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-center" style="min-width: 160px; min-height: 160px;">
                        <div id="batchQrCanvas" style="width: 160px; height: 160px;"></div>
                    </div>
                </div>
                <div class="fw-bold font-monospace text-primary fs-6 mt-1"><?= htmlspecialchars($outward['batch_no']) ?></div>
            </div>

            <!-- Outward Dispatch Details -->
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                        <i class="bi bi-info-circle text-primary"></i> Outward Dispatch Details
                    </h6>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size: 0.72rem;">Traceability</span>
                </div>

                <div class="small mb-2 d-flex justify-content-between">
                    <span class="text-muted">Outward Gate Pass:</span>
                    <span class="fw-bold font-monospace text-primary"><?= htmlspecialchars($outward['gate_pass_no'] ?: $outward['outward_no']) ?></span>
                </div>
                <div class="small mb-2 d-flex justify-content-between">
                    <span class="text-muted">Production Batch:</span>
                    <span class="fw-bold font-monospace text-dark"><?= htmlspecialchars($outward['batch_no']) ?></span>
                </div>
                <div class="small mb-2 d-flex justify-content-between">
                    <span class="text-muted">Program #:</span>
                    <span class="fw-semibold font-monospace text-muted"><?= htmlspecialchars($outward['program_no']) ?></span>
                </div>
                <div class="small mb-3 d-flex justify-content-between">
                    <span class="text-muted">Product / Style:</span>
                    <span class="fw-bold text-dark text-end"><?= htmlspecialchars($outward['program_name']) ?></span>
                </div>

                <div class="p-2.5 rounded-2" style="background: #fffbeb; border: 1px solid #fef3c7;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="small fw-semibold text-dark"><i class="bi bi-geo-alt-fill text-warning me-1"></i> Origin Unit:</span>
                        <span class="badge bg-warning bg-opacity-50 text-dark font-monospace"><?= htmlspecialchars($outward['source_unit_code']) ?></span>
                    </div>
                    <div class="small fw-bold text-dark"><?= htmlspecialchars($outward['source_unit_name']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($outward['manager_name']) ?></div>
                </div>
            </div>
        </div>

        <!-- Right Column: Live Cross-Check Scanner & Variant Breakdown -->
        <div class="col-12 col-lg-8">
            <!-- Card 1: Live Product & QR Cross-Check Scanner -->
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                        <i class="bi bi-upc-scan text-primary"></i> Live Product & QR Cross-Check Scanner
                    </h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size: 0.72rem;">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Inward to: <?= htmlspecialchars($outward['dest_warehouse_name']) ?> (<?= htmlspecialchars($outward['dest_warehouse_code']) ?>)
                    </span>
                </div>

                <div class="input-group input-group-lg my-2 shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-qr-code fs-4"></i></span>
                    <input type="text" class="form-control border-start-0 border-end-0 font-monospace fs-6" id="inputQrScanner" placeholder="<?= \App\Core\Auth::can('inward', 'add') ? 'Scan Variant QR / Barcode / SKU / Master Carton...' : 'View-only access - scanner disabled' ?>" autofocus autocomplete="off" <?= !\App\Core\Auth::can('inward', 'add') ? 'disabled' : '' ?>>
                    <button class="btn btn-primary px-4 fw-bold d-flex align-items-center gap-1" type="button" onclick="processManualScan()" <?= !\App\Core\Auth::can('inward', 'add') ? 'disabled' : '' ?>>
                        <i class="bi bi-upc-scan"></i> Scan
                    </button>
                </div>
                <div id="scanFeedbackAlert" class="small mt-1 text-muted" style="min-height: 20px;">
                    <i class="bi bi-info-circle me-1"></i> Ready for barcode gun scan, camera QR scan, or manual entry.
                </div>
            </div>

            <!-- Card 2: Variant Breakdown & Inward Reconcile Table -->
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-table text-primary"></i> Variant Breakdown & Inward Reconcile Table
                    </h6>
                    <p class="small text-muted mb-0 mt-0.5">Outward cross-check and scanned received/damaged counts</p>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="tableReconcile">
                        <thead class="table-light small text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                            <tr>
                                <th style="width: 40px;" class="text-center">NO</th>
                                <th>PRODUCT & SKU</th>
                                <th>OUTWARD QR / CODE</th>
                                <th>SIZE & COLOR</th>
                                <th>OUTWARD EXP</th>
                                <th style="width: 170px;">VERIFIED & DAMAGED</th>
                                <th>DIFFERENCE</th>
                                <th>STATUS</th>
                                <th class="text-center" style="width: 70px;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $idx => $it): ?>
                            <tr id="rowVariant-<?= (int)$it['variant_id'] ?>"
                                data-variant-id="<?= (int)$it['variant_id'] ?>"
                                data-sku-code="<?= htmlspecialchars((string)$it['sku_code'], ENT_QUOTES, 'UTF-8') ?>"
                                data-tag-code="<?= htmlspecialchars((string)$it['tag_code'], ENT_QUOTES, 'UTF-8') ?>"
                                data-product-name="<?= htmlspecialchars((string)$it['variant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-credited-qty="<?= (int)$it['credited_qty'] ?>"
                                data-damaged-qty="<?= (int)$it['damaged_qty'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-2 p-2 bg-light border text-primary">
                                            <i class="bi bi-box fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($it['variant_name']) ?></div>
                                            <div class="font-monospace small text-muted"><?= htmlspecialchars($it['sku_code']) ?></div>
                                            <span class="badge bg-light text-muted border" style="font-size: 0.68rem;"><?= htmlspecialchars($it['category_name'] ?: '—') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-monospace small text-dark fw-semibold"><?= htmlspecialchars((string)$it['tag_code']) ?: 'QR unavailable' ?></div>
                                    <div class="small text-muted fw-semibold font-monospace" style="font-size: 0.75rem;">SKU: <?= htmlspecialchars((string)$it['sku_code']) ?></div>
                                </td>
                                <td>
                                    <div><span class="badge bg-light text-dark border"><?= htmlspecialchars((string)($it['size'] ?? '') ?: '—') ?></span></div>
                                    <div class="mt-1"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><?= htmlspecialchars((string)($it['color'] ?? '') ?: '—') ?></span></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= number_format($it['dispatched_qty']) ?> pcs</div>
                                    <div class="small text-success fw-semibold"><?= number_format($it['credited_qty']) ?> Credited</div>
                                    <div class="small text-muted font-monospace"><?= number_format($it['remaining_qty']) ?> Remaining</div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 mb-1 align-items-center">
                                        <span class="small fw-bold text-success text-uppercase" style="width: 55px; font-size: 0.68rem;">VERIFIED</span>
                                        <input type="number" min="0" max="<?= (int)$it['remaining_qty'] ?>" class="form-control form-control-sm text-center fw-bold border-success input-verified" style="background: #f0fdf4;" value="0" onchange="recalculateTotals()" onkeyup="recalculateTotals()">
                                    </div>
                                    <div class="d-flex gap-1 align-items-center">
                                        <span class="small fw-bold text-danger text-uppercase" style="width: 55px; font-size: 0.68rem;">DAMAGED</span>
                                        <input type="number" min="0" max="<?= (int)$it['remaining_qty'] ?>" class="form-control form-control-sm text-center fw-bold border-danger input-damaged" style="background: #fef2f2;" value="0" onchange="recalculateTotals()" onkeyup="recalculateTotals()">
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-danger text-white px-2 py-1.5 font-monospace diff-badge">-<?= (int)$it['remaining_qty'] ?> pcs</span>
                                </td>
                                <td>
                                    <span class="badge border border-danger text-danger bg-white px-2 py-1.5 fw-semibold status-badge">SHORT (<?= (int)$it['remaining_qty'] ?>)</span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 mx-auto" onclick="openPieceModal(<?= (int)$outward['id'] ?>, <?= (int)$it['variant_id'] ?>)" title="View Individual Piece QR Codes">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bottom Warning / Progress Banner -->
            <div class="alert alert-warning border-warning d-flex justify-content-between align-items-center rounded-3 p-3 mb-3" style="background: #fffbeb;">
                <div class="small fw-medium text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                    <div>
                        <strong>Partial Inward Batch:</strong> <span id="bannerCreditedText"><?= (int)$totalCredited ?></span> pcs have already been credited. Scan or enter remaining <span id="bannerPendingText"><?= (int)$pendingMissing ?></span> pcs below to credit incremental stock to warehouse.
                    </div>
                </div>
                <span class="badge bg-danger text-white px-2.5 py-1.5 font-monospace fs-7 text-nowrap ms-2" id="bannerPendingBadge">
                    <?= (int)$pendingMissing ?> pcs pending
                </span>
            </div>

            <!-- Bottom CTA Button / Form Submission -->
            <?php if (\App\Core\Auth::can('inward', 'add')): ?>
            <form action="<?= \App\Core\Controller::url('/m/inward/credit') ?>" method="POST" id="formCreditStock" onsubmit="return validateCreditSubmission(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="outward_id" value="<?= (int)$outward['id'] ?>">
                <input type="hidden" name="verified_qty" id="submitVerifiedQty" value="0">
                <input type="hidden" name="damaged_qty" id="submitDamagedQty" value="0">
                <input type="hidden" name="items_json" id="submitItemsJson" value="[]">
                <input type="hidden" name="damage_reason" id="submitDamageReason" value="Transit handling defects">

                <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow py-3 d-flex align-items-center justify-content-center gap-2 text-uppercase" id="btnSubmitStock" style="letter-spacing: 0.03em;">
                    <i class="bi bi-box-arrow-in-down fs-4"></i>
                    Update / Credit Inward Stock (+<span id="btnIncrementalQty">0</span> pcs) to <?= htmlspecialchars($outward['dest_warehouse_name']) ?> Inventory
                </button>
            </form>
            <?php else: ?>
            <div class="alert alert-secondary text-center small py-3 mb-0 rounded-3">
                <i class="bi bi-eye me-1"></i> View-only access: Inward stock crediting is restricted to staff with create/add permissions.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================================================= -->
<!-- 3. MODAL: Product Specifications & All Individual Piece QR Codes (Image 3) -->
<!-- ========================================================================================= -->
<div class="modal fade" id="modalPieceQrViewer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <!-- Modal Header -->
            <div class="modal-header py-2.5 px-3 bg-dark text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="fs-5">📦</span>
                    <h6 class="modal-title fw-bold mb-0 text-white">Product Specifications & All Individual Piece QR Codes</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-2.5 py-1.5 font-monospace">WH: <?= htmlspecialchars($outward['dest_warehouse_name']) ?> (<?= htmlspecialchars($outward['dest_warehouse_code']) ?>)</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-3 bg-light">
                <!-- Top Section: Big Piece QR Code Preview & Mode Switcher -->
                <div class="row g-3 mb-3 bg-white p-3 rounded-3 shadow-sm mx-0 align-items-center">
                    <!-- Top Left: QR Preview -->
                    <div class="col-12 col-md-4 text-center border-end">
                        <div class="d-inline-block p-2 border rounded-2 bg-light shadow-sm">
                            <div id="modalPieceQrCanvas" class="d-flex align-items-center justify-content-center" style="width: 140px; height: 140px;"></div>
                        </div>
                        <div class="fw-bold font-monospace fs-5 text-primary mt-1" id="modalCurrentPieceTag">—</div>
                    </div>

                    <!-- Top Right: Mode Switcher & CTA -->
                    <div class="col-12 col-md-8 ps-md-4">
                        <div class="small fw-semibold text-muted text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                            <i class="bi bi-toggles me-1"></i> Scanner Mode (Click to Switch):
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-success fw-bold px-3 py-2 flex-grow-1 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnModeGood" onclick="setScanMode('good')">
                                <i class="bi bi-check-circle-fill"></i> Good / Intact Mode
                            </button>
                            <button type="button" class="btn btn-outline-danger fw-semibold px-3 py-2 flex-grow-1 d-flex align-items-center justify-content-center gap-2" id="btnModeDamaged" onclick="setScanMode('damaged')">
                                <i class="bi bi-slash-circle"></i> Damaged / Inactivate Mode
                            </button>
                        </div>

                        <button type="button" class="btn btn-success btn-lg w-100 fw-bold py-2.5 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnInwardNextPiece" onclick="inwardNextPiece()">
                            <i class="bi bi-check2-circle fs-5"></i> Inward Next Piece (Good Product)
                        </button>
                    </div>
                </div>

                <!-- Sub-Section Header & Filter Tabs -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 px-1">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                        <i class="bi bi-grid-3x3-gap text-secondary"></i>                         All Dispatched Pieces (<span id="modalTotalPiecesText">0</span> total)
                    </h6>
                    <!-- Filter Tabs -->
                    <div class="d-flex gap-1.5 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-danger fw-semibold rounded-pill px-2.5 py-1 piece-tab-btn" id="tabMismatched" onclick="filterModalPieces('mismatched')">
                            Only Mismatched (<span id="modalCountMismatched">0</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-dark fw-bold rounded-pill px-2.5 py-1 piece-tab-btn active" id="tabAll" onclick="filterModalPieces('all')">
                            All Pieces (<span id="modalCountAll">0</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success fw-semibold rounded-pill px-2.5 py-1 piece-tab-btn" id="tabVerified" onclick="filterModalPieces('verified')">
                            Verified (<span id="modalCountVerified">0</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger fw-semibold rounded-pill px-2.5 py-1 piece-tab-btn" id="tabDamaged" onclick="filterModalPieces('damaged')">
                            Damaged (<span id="modalCountDamaged">0</span>)
                        </button>
                    </div>
                </div>

                <!-- 2-Column Responsive Piece Grid -->
                <div class="row g-2 overflow-auto" id="modalPieceCardGrid" style="max-height: 440px;">
                    <!-- Dynamically populated piece cards -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-white py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include QR Code Library -->
<script src="<?= \App\Core\Controller::url('/assets/js/qrcode.min.js') ?>"></script>

<!-- Interactive Logic for Image 2 & Image 3 -->
<script>
// Global State
let pieceData = [];
let scanMode = 'good'; // 'good' or 'damaged'
let totalDispatchedConst = <?= (int)$totalDispatched ?>;
let totalCreditedConst = <?= (int)$totalCredited ?>;
let totalDamagedConst = <?= (int)$totalDamaged ?>;
let activeModalOutwardId = <?= (int)$outward['id'] ?>;
let activeModalVariantId = <?= (int)($items[0]['variant_id'] ?? 0) ?>;

// Audio Chime Synthesizer using Web Audio API
function playSound(type) {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(600, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1000, audioCtx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.15);
        } else {
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(300, audioCtx.currentTime);
            osc.frequency.setValueAtTime(200, audioCtx.currentTime + 0.2);
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.25);
        }
    } catch (e) {
        // Fallback silent
    }
}

// Render only a real QR code; never substitute a decorative pattern.
function renderQrToCanvas(targetId, text, width, height) {
    const el = document.getElementById(targetId);
    if (!el) return;

    el.innerHTML = '';
    if (!text || typeof QRCode === 'undefined') {
        el.textContent = 'QR unavailable';
        return;
    }

    try {
        new QRCode(el, {
            text: text,
            width: width || 140,
            height: height || 140,
            colorDark: "#0f172a",
            colorLight: "#ffffff",
            correctLevel: (QRCode.CorrectLevel && QRCode.CorrectLevel.M) ? QRCode.CorrectLevel.M : 0
        });
    } catch (error) {
        console.error('Could not render the outward item QR code:', error);
        el.textContent = 'QR unavailable';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    renderQrToCanvas('batchQrCanvas', '<?= htmlspecialchars($outward['batch_no']) ?>');
    // Enter key handling for inputQrScanner
    const scannerInput = document.getElementById('inputQrScanner');
    if (scannerInput) {
        scannerInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processManualScan();
            }
        });
    }
});

// Process Live Scan
function processManualScan() {
    const input = document.getElementById('inputQrScanner');
    const val = (input.value || '').trim();
    const feedback = document.getElementById('scanFeedbackAlert');

    if (!val) {
        feedback.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i> Please scan or type a piece tag, SKU, or QR code.</span>';
        playSound('error');
        return;
    }

    // Match in reconcile table
    const scanCode = val.toUpperCase();
    const matchedRow = Array.from(document.querySelectorAll('#tableReconcile tbody tr')).find(row => {
        return [row.dataset.tagCode, row.dataset.skuCode, row.dataset.variantId]
            .some(code => code && code.toUpperCase() === scanCode);
    });
    if (!matchedRow) {
        feedback.innerHTML = `<span class="text-danger fw-semibold"><i class="bi bi-x-circle me-1"></i> '${escapeHtml(val)}' is not an item in this outward consignment.</span>`;
        playSound('error');
        return;
    }

    const verifiedInput = matchedRow.querySelector('.input-verified');
    const cur = parseInt(verifiedInput.value, 10) || 0;
    const max = parseInt(verifiedInput.getAttribute('max'), 10) || 0;
    if (cur >= max) {
        feedback.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> All dispatched pieces for ${escapeHtml(matchedRow.dataset.productName)} have already been verified.</span>`;
        playSound('error');
        input.value = '';
        return;
    }

    verifiedInput.value = cur + 1;
    recalculateTotals();
    feedback.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Scanned ${escapeHtml(matchedRow.dataset.productName)} (${escapeHtml(matchedRow.dataset.skuCode)}). +1 verified.</span>`;
    playSound('success');
    input.value = '';
    input.focus();
}

// Recalculate Table and KPI metrics
function recalculateTotals() {
    let incVerified = 0;
    let incDamaged = 0;

    document.querySelectorAll('#tableReconcile tbody tr').forEach(row => {
        const vInput = row.querySelector('.input-verified');
        const dInput = row.querySelector('.input-damaged');
        const diffBadge = row.querySelector('.diff-badge');
        const statusBadge = row.querySelector('.status-badge');

        const vVal = parseInt(vInput.value) || 0;
        const dVal = parseInt(dInput.value) || 0;
        const maxVal = parseInt(vInput.getAttribute('max'), 10) || 0;

        // Validation clamps
        if (vVal < 0) vInput.value = 0;
        if (dVal < 0) dInput.value = 0;
        if (dVal > maxVal) dInput.value = maxVal;
        if (vVal + dVal > maxVal) {
            vInput.value = Math.max(0, maxVal - dVal);
        }

        const effectiveV = parseInt(vInput.value) || 0;
        const effectiveD = parseInt(dInput.value) || 0;

        incVerified += effectiveV;
        incDamaged += effectiveD;

        const diff = maxVal - effectiveV - effectiveD;
        if (diff <= 0) {
            diffBadge.textContent = '0 pcs';
            diffBadge.className = 'badge bg-success text-white px-2 py-1.5 font-monospace diff-badge';
            statusBadge.textContent = 'MATCHED';
            statusBadge.className = 'badge bg-success text-white px-2 py-1.5 fw-semibold status-badge';
        } else {
            diffBadge.textContent = `-${diff} pcs`;
            diffBadge.className = 'badge bg-danger text-white px-2 py-1.5 font-monospace diff-badge';
            statusBadge.textContent = `SHORT (${diff})`;
            statusBadge.className = 'badge border border-danger text-danger bg-white px-2 py-1.5 fw-semibold status-badge';
        }
    });

    // Update Top KPIs
    const newVerifiedTotal = totalCreditedConst + incVerified;
    const newDamagedTotal = totalDamagedConst + incDamaged;
    const newPendingTotal = Math.max(0, totalDispatchedConst - newVerifiedTotal - newDamagedTotal);
    const newMatch = totalDispatchedConst > 0 ? Math.round((newVerifiedTotal / totalDispatchedConst) * 100) : 0;

    document.getElementById('statVerified').textContent = newVerifiedTotal;
    document.getElementById('statDamaged').textContent = newDamagedTotal;
    document.getElementById('statQuarantine').textContent = newDamagedTotal;
    document.getElementById('statPending').textContent = newPendingTotal;
    document.getElementById('statMatch').textContent = newMatch;

    // Update CTA button & banner
    document.getElementById('btnIncrementalQty').textContent = incVerified;
    document.getElementById('submitVerifiedQty').value = incVerified;
    document.getElementById('submitDamagedQty').value = incDamaged;
    const inwardItems = Array.from(document.querySelectorAll('#tableReconcile tbody tr')).map(row => {
        const verified = parseInt(row.querySelector('.input-verified').value, 10) || 0;
        const damaged = parseInt(row.querySelector('.input-damaged').value, 10) || 0;
        return {
            variant_id: parseInt(row.dataset.variantId, 10),
            verified_qty: verified,
            damaged_qty: damaged
        };
    }).filter(item => item.verified_qty > 0 || item.damaged_qty > 0);
    document.getElementById('submitItemsJson').value = JSON.stringify(inwardItems);

    document.getElementById('bannerCreditedText').textContent = newVerifiedTotal;
    document.getElementById('bannerPendingText').textContent = newPendingTotal;
    document.getElementById('bannerPendingBadge').textContent = `${newPendingTotal} pcs pending`;
}

// Validate Submission
function validateCreditSubmission(e) {
    const vQty = parseInt(document.getElementById('submitVerifiedQty').value) || 0;
    const dQty = parseInt(document.getElementById('submitDamagedQty').value) || 0;

    if (vQty <= 0 && dQty <= 0) {
        alert('Please scan or enter at least 1 verified or damaged piece before updating inventory stock.');
        e.preventDefault();
        return false;
    }

    if (!confirm(`Confirm Inward Acceptance?\n\nVerified: +${vQty} pcs will be credited directly to warehouse inventory.\nDamaged: ${dQty} pcs will be quarantined.`)) {
        e.preventDefault();
        return false;
    }

    document.getElementById('btnSubmitStock').disabled = true;
    document.getElementById('btnSubmitStock').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Crediting Stock to Inventory...';
    return true;
}

// Modal Logic (Image 3)
function openPieceModal(outwardId, variantId) {
    activeModalOutwardId = outwardId;
    activeModalVariantId = variantId;

    const modal = new bootstrap.Modal(document.getElementById('modalPieceQrViewer'));
    modal.show();

    // Fetch pieces
    const grid = document.getElementById('modalPieceCardGrid');
    grid.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary"></div><div class="small text-muted mt-2">Loading individual piece barcodes...</div></div>';

    fetch(`<?= \App\Core\Controller::url('/m/inward/pieces/') ?>${outwardId}?variant_id=${variantId}`)
        .then(res => res.json().then(data => {
            if (!res.ok || !data.success) {
                throw new Error(data.error || `HTTP ${res.status}: ${res.statusText}`);
            }
            return data;
        }))
        .then(data => {
            pieceData = Array.isArray(data.pieces) ? data.pieces : [];
            const totEl = document.getElementById('modalTotalPiecesText');
            if (totEl) totEl.textContent = data.total_pieces;

            renderPieceCards('all');
            updateModalCounters();

            if (data.next_tag) {
                const tagEl = document.getElementById('modalCurrentPieceTag');
                if (tagEl) tagEl.textContent = data.next_tag;
                renderQrToCanvas('modalPieceQrCanvas', data.next_tag, 140, 140);
            }
        })
        .catch(err => {
            console.error("Piece fetch error:", err);
            grid.innerHTML = `<div class="col-12 text-danger text-center py-4">Error loading pieces data. (${err.message || 'Network / parsing issue'})</div>`;
        });
}

function setScanMode(mode) {
    scanMode = mode;
    const btnGood = document.getElementById('btnModeGood');
    const btnDamaged = document.getElementById('btnModeDamaged');
    const btnNext = document.getElementById('btnInwardNextPiece');

    if (mode === 'good') {
        btnGood.className = 'btn btn-success fw-bold px-3 py-2 flex-grow-1 shadow-sm d-flex align-items-center justify-content-center gap-2';
        btnDamaged.className = 'btn btn-outline-danger fw-semibold px-3 py-2 flex-grow-1 d-flex align-items-center justify-content-center gap-2';
        btnNext.className = 'btn btn-success btn-lg w-100 fw-bold py-2.5 shadow-sm d-flex align-items-center justify-content-center gap-2';
        btnNext.innerHTML = '<i class="bi bi-check2-circle fs-5"></i> Inward Next Piece (Good Product)';
    } else {
        btnDamaged.className = 'btn btn-danger fw-bold px-3 py-2 flex-grow-1 shadow-sm d-flex align-items-center justify-content-center gap-2';
        btnGood.className = 'btn btn-outline-success fw-semibold px-3 py-2 flex-grow-1 d-flex align-items-center justify-content-center gap-2';
        btnNext.className = 'btn btn-danger btn-lg w-100 fw-bold py-2.5 shadow-sm d-flex align-items-center justify-content-center gap-2';
        btnNext.innerHTML = '<i class="bi bi-slash-circle fs-5"></i> Inward Next Piece (Damaged / Inactive)';
    }
}

function renderPieceCards(filter) {
    const grid = document.getElementById('modalPieceCardGrid');
    if (!grid) return;
    grid.innerHTML = '';

    if (!Array.isArray(pieceData) || pieceData.length === 0) {
        grid.innerHTML = '<div class="col-12 text-muted text-center py-4">No individual piece barcodes found.</div>';
        return;
    }

    const fragment = document.createDocumentFragment();

    pieceData.forEach(p => {
        if (filter === 'mismatched' && p.status !== 'unverified') return;
        if (filter === 'verified' && p.status !== 'verified') return;
        if (filter === 'damaged' && p.status !== 'damaged') return;

        const col = document.createElement('div');
        col.className = 'col-12 col-md-6';
        col.innerHTML = `
            <div class="card border rounded-3 p-2 bg-white shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                    <span class="badge bg-dark font-monospace" style="font-size: 0.72rem;">${escapeHtml(p.tag_code)}</span>
                    <span class="small fw-bold text-muted font-monospace">Disc: ₹${parseFloat(p.discount_price || 0).toFixed(2)}</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="border rounded-2 p-1 bg-light text-center" style="width: 55px; height: 55px; min-width: 55px;">
                        <i class="bi bi-qr-code text-primary fs-3"></i>
                    </div>
                    <div class="flex-grow-1" style="line-height: 1.2;">
                        <div class="fw-bold text-dark small text-truncate" style="max-width: 170px;">${escapeHtml(p.product_name)}</div>
                        <div class="small text-muted font-monospace" style="font-size: 0.68rem;">ID: ${Number(p.variant_id)} · SKU: ${escapeHtml(p.sku_code)}</div>
                        <div class="small text-muted font-monospace" style="font-size: 0.72rem;">${escapeHtml(p.size || '—')}-${escapeHtml(p.color || '—')}</div>
                        <div class="small text-muted" style="font-size: 0.7rem;">MRP: ₹${parseFloat(p.mrp || 0).toFixed(2)}</div>
                        <div class="small text-dark fw-bold font-monospace" style="font-size: 0.72rem;">Disc: ₹${parseFloat(p.discount_price || 0).toFixed(2)}</div>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm ${p.status === 'verified' ? 'btn-success' : 'btn-outline-success'} p-1 px-2" onclick="togglePiece(${p.index}, 'verified')" title="Mark Verified">
                            <i class="bi bi-check2"></i>
                        </button>
                        <button type="button" class="btn btn-sm ${p.status === 'damaged' ? 'btn-danger' : 'btn-outline-danger'} p-1 px-2" onclick="togglePiece(${p.index}, 'damaged')" title="Mark Damaged">
                            <i class="bi bi-slash-circle"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        fragment.appendChild(col);
    });

    grid.appendChild(fragment);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (character) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
}

function filterModalPieces(tab) {
    document.querySelectorAll('.piece-tab-btn').forEach(b => {
        b.classList.remove('active', 'btn-dark');
        if (b.id === 'tabAll') b.classList.add('btn-outline-dark');
    });

    const activeBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    if (activeBtn) {
        activeBtn.classList.add('active', 'btn-dark');
        activeBtn.classList.remove('btn-outline-dark', 'btn-outline-danger', 'btn-outline-success');
    }

    renderPieceCards(tab);
}

function togglePiece(index, targetStatus) {
    const item = pieceData.find(p => p.index === index);
    if (!item) return;

    if (item.status === targetStatus) {
        item.status = 'unverified';
    } else {
        item.status = targetStatus;
    }

    playSound('success');
    renderPieceCards('all');
    updateModalCounters();
    syncModalToReconcileTable();
}

function inwardNextPiece() {
    const targetStatus = scanMode === 'good' ? 'verified' : 'damaged';
    const nextUnverified = pieceData.find(p => p.status === 'unverified');

    if (!nextUnverified) {
        alert('All pieces in this batch have already been verified or inspected!');
        return;
    }

    nextUnverified.status = targetStatus;
    playSound('success');

    // Update big QR to the next one
    const afterNext = pieceData.find(p => p.status === 'unverified');
    if (afterNext) {
        document.getElementById('modalCurrentPieceTag').textContent = afterNext.tag_code;
        renderQrToCanvas('modalPieceQrCanvas', afterNext.tag_code);
    }

    renderPieceCards('all');
    updateModalCounters();
    syncModalToReconcileTable();
}

function updateModalCounters() {
    if (!Array.isArray(pieceData)) return;
    const total = pieceData.length;
    const verified = pieceData.filter(p => p.status === 'verified').length;
    const damaged = pieceData.filter(p => p.status === 'damaged').length;
    const mismatched = total - verified - damaged;

    const elAll = document.getElementById('modalCountAll');
    const elVer = document.getElementById('modalCountVerified');
    const elDam = document.getElementById('modalCountDamaged');
    const elMis = document.getElementById('modalCountMismatched');

    if (elAll) elAll.textContent = total;
    if (elVer) elVer.textContent = verified;
    if (elDam) elDam.textContent = damaged;
    if (elMis) elMis.textContent = Math.max(0, mismatched);
}

function syncModalToReconcileTable() {
    const currentModalVerified = pieceData.filter(p => p.status === 'verified').length;
    const currentModalDamaged = pieceData.filter(p => p.status === 'damaged').length;

    const row = document.getElementById(`rowVariant-${activeModalVariantId}`);
    if (row) {
        const incrementalVerified = Math.max(0, currentModalVerified - (parseInt(row.dataset.creditedQty, 10) || 0));
        const incrementalDamaged = Math.max(0, currentModalDamaged - (parseInt(row.dataset.damagedQty, 10) || 0));
        const vInput = row.querySelector('.input-verified');
        const dInput = row.querySelector('.input-damaged');
        if (vInput) vInput.value = incrementalVerified;
        if (dInput) dInput.value = incrementalDamaged;
        recalculateTotals();
    }
}
</script>
