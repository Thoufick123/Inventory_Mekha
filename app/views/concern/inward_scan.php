<?php
// app/views/concern/inward_scan.php - Live Cross-Check, QR Scanning & Inward Reconcile
$isDamageTransfer = (int)($outward['damage_transfer'] ?? 0) === 1;
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
                    <li class="breadcrumb-item active text-dark fw-medium" aria-current="page"><?= $isDamageTransfer ? 'Damage Outward' : 'Cross-Check Batch' ?> #<?= htmlspecialchars($isDamageTransfer ? $outward['outward_no'] : $outward['batch_no']) ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-in-down text-primary"></i> <?= $isDamageTransfer ? 'Receive Damage Outward' : 'Complete Inward Shipment' ?> <span class="text-muted fs-6 font-monospace">#<?= htmlspecialchars($isDamageTransfer ? $outward['outward_no'] : $outward['batch_no']) ?></span>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($outward['batch_id'])): ?>
            <a href="<?= \App\Core\Controller::url('/production/batches/label/' . (int)$outward['batch_id']) ?>" target="_blank" class="btn btn-outline-info btn-sm px-3 shadow-sm d-flex align-items-center gap-1">
                <i class="bi bi-printer"></i> Print Batch Labels
            </a>
            <?php endif; ?>
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
                        <div class="small fw-semibold text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;"><?= $isDamageTransfer ? 'DAMAGED STOCK RECEIVED' : 'INWARD VERIFIED (INTACT)' ?></div>
                        <div class="fs-4 fw-bold text-dark mt-1"><span id="statVerified"><?= number_format($isDamageTransfer ? $totalCredited + $totalDamaged : $totalCredited) ?></span> pcs</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #d1fae5; color: #10b981;">
                        <i class="bi bi-qr-code-scan fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Damaged / Inactive -->
        <div class="col-12 col-sm-6 col-lg <?= $isDamageTransfer ? 'd-none' : '' ?>">
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
                        <div class="small fw-semibold text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;"><?= $isDamageTransfer ? 'RECEIPT MATCH' : 'VERIFICATION MATCH' ?></div>
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
            <?php $masterOutwardQrCode = trim((string)(($outward['outward_no'] ?? '') ?: ($outward['batch_no'] ?? ''))); ?>
            <div class="card border-0 shadow-sm rounded-3 p-3 <?= $isDamageTransfer ? 'bg-danger bg-opacity-10' : 'bg-white' ?> mb-3 text-center">
                <div class="small fw-bold <?= $isDamageTransfer ? 'text-danger' : 'text-muted' ?> text-uppercase mb-2" style="font-size: 0.75rem;">
                    <?php if ($isDamageTransfer): ?>
                        <i class="bi bi-exclamation-triangle me-1"></i> Damage Transfer Manifest QR
                    <?php else: ?>
                        <i class="bi bi-qr-code me-1 text-primary"></i> Outward Master QR Code
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-center py-2">
                    <div id="batchCartonQrContainer" class="p-2 border rounded-2 bg-white d-flex align-items-center justify-content-center" style="min-width: 150px; min-height: 150px;">
                        <div id="batchQrCanvas" style="width: 150px; height: 150px;"></div>
                    </div>
                </div>
                <div class="fw-bold font-monospace <?= $isDamageTransfer ? 'text-dark' : 'text-primary' ?> fs-6 mt-1"><?= htmlspecialchars($masterOutwardQrCode) ?></div>
                <?php if ($isDamageTransfer): ?>
                    <div class="small text-muted mt-1">All received pieces remain quarantined.</div>
                <?php endif; ?>
                <?php if (\App\Core\Auth::can('inward', 'add')): ?>
                    <button type="button" class="btn btn-sm <?= $isDamageTransfer ? 'btn-danger' : 'btn-primary' ?> mt-2 w-100 fw-semibold" onclick="bulkScanOutwardMaster()">
                        <i class="bi bi-qr-code-scan me-1"></i> Scan All (Bulk Inward)
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Live Cross-Check Scanner & Variant Breakdown -->
        <div class="col-12 col-lg-8">
            <!-- Card 1: Live Product & QR Cross-Check Scanner -->
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                        <i class="bi bi-upc-scan text-primary"></i> <?= $isDamageTransfer ? 'Live Damaged Stock Receipt Scanner' : 'Live Product & QR Cross-Check Scanner' ?>
                    </h6>
                </div>

                <div class="input-group input-group-lg my-2 shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-qr-code fs-4"></i></span>
                    <input type="text" class="form-control border-start-0 border-end-0 font-monospace fs-6" id="inputQrScanner" placeholder="<?= \App\Core\Auth::can('inward', 'add') ? 'Scan Variant QR / Barcode / Product Code / Master Carton...' : 'View-only access - scanner disabled' ?>" autofocus autocomplete="off" <?= !\App\Core\Auth::can('inward', 'add') ? 'disabled' : '' ?>>
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
                    <p class="small text-muted mb-0 mt-0.5"><?= $isDamageTransfer ? 'Scan the transferred damaged items; all received quantities remain quarantined.' : 'Outward cross-check and scanned received/damaged counts' ?></p>
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
                                <th style="width: 170px;"><?= $isDamageTransfer ? 'DAMAGED RECEIVED' : 'VERIFIED & DAMAGED' ?></th>
                                <th>DIFFERENCE</th>
                                <th>STATUS</th>
                                <th class="text-center" style="width: 70px;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $idx => $it): ?>
                            <tr id="rowVariant-<?= (int)$it['variant_id'] ?>"
                                data-variant-id="<?= (int)$it['variant_id'] ?>"
                                data-product-code="<?= htmlspecialchars((string)$it['product_code'], ENT_QUOTES, 'UTF-8') ?>"
                                data-tag-code="<?= htmlspecialchars((string)$it['tag_code'], ENT_QUOTES, 'UTF-8') ?>"
                                data-product-name="<?= htmlspecialchars((string)$it['variant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-size="<?= htmlspecialchars((string)($it['size'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-color="<?= htmlspecialchars((string)($it['color'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-mrp="<?= (float)($it['mrp'] ?? 0) ?>"
                                data-price="<?= (float)($it['price'] ?? 0) ?>"
                                data-attributes="<?= htmlspecialchars(json_encode($it['extra_attributes'] ?? []), ENT_QUOTES, 'UTF-8') ?>"
                                data-category="<?= htmlspecialchars((string)($it['category_name'] ?? 'Finished Garment'), ENT_QUOTES, 'UTF-8') ?>"
                                data-dispatched-qty="<?= (int)$it['dispatched_qty'] ?>"
                                data-credited-qty="<?= (int)$it['credited_qty'] ?>"
                                data-damaged-qty="<?= (int)$it['damaged_qty'] ?>"
                                data-remaining-qty="<?= (int)$it['remaining_qty'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-2 p-2 bg-light border text-primary">
                                            <i class="bi bi-box fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($it['variant_name']) ?></div>
                                            <div class="font-monospace small text-muted"><?= htmlspecialchars($it['product_code']) ?></div>
                                            <span class="badge bg-light text-muted border" style="font-size: 0.68rem;"><?= htmlspecialchars($it['category_name'] ?: '—') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-monospace small text-dark fw-semibold"><?= htmlspecialchars((string)$it['tag_code']) ?: 'QR unavailable' ?></div>
                                    <div class="small text-muted fw-semibold font-monospace" style="font-size: 0.75rem;">Product Code: <?= htmlspecialchars((string)$it['product_code']) ?></div>
                                </td>
                                <td>
                                    <div><span class="badge bg-light text-dark border"><?= htmlspecialchars((string)($it['size'] ?? '') ?: '—') ?></span></div>
                                    <div class="mt-1"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><?= htmlspecialchars((string)($it['color'] ?? '') ?: '—') ?></span></div>
                                    <?php if (!empty($it['extra_attributes']) && is_array($it['extra_attributes'])): ?>
                                        <div class="mt-1 d-flex flex-wrap gap-1">
                                            <?php foreach ($it['extra_attributes'] as $ak => $av): ?>
                                                <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;"><?= htmlspecialchars((string)$ak) ?>: <?= htmlspecialchars((string)$av) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= number_format($it['dispatched_qty']) ?> pcs</div>
                                    <div class="small text-success fw-semibold"><?= number_format($it['credited_qty']) ?> Credited</div>
                                    <div class="small text-muted font-monospace"><?= number_format($it['remaining_qty']) ?> Remaining</div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 mb-1 align-items-center <?= $isDamageTransfer ? 'd-none' : '' ?>">
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
                        <strong><?= $isDamageTransfer ? 'Partial Damage Outward:' : 'Partial Inward Batch:' ?></strong> <span id="bannerCreditedText"><?= $isDamageTransfer ? (int)($totalCredited + $totalDamaged) : (int)$totalCredited ?></span> pcs have already been received. Scan or enter remaining <span id="bannerPendingText"><?= (int)$pendingMissing ?></span> pcs below<?= $isDamageTransfer ? ' to quarantine them at this concern.' : ' to credit incremental stock to warehouse.' ?>
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
                <input type="hidden" name="move_to_labels" id="submitMoveToLabels" value="0">

                <div class="row g-2">
                    <div class="col-12">
                        <button type="submit" onclick="document.getElementById('submitMoveToLabels').value='0'" class="btn btn-success btn-lg w-100 fw-bold shadow py-3 d-flex align-items-center justify-content-center gap-2 text-uppercase" id="btnSubmitStock" style="letter-spacing: 0.03em;">
                            <i class="bi bi-box-arrow-in-down fs-4"></i>
                            <?= $isDamageTransfer ? 'Receive Damaged Stock' : 'Add to Stock' ?> (+<span id="btnIncrementalQty">0</span> pcs) to <?= htmlspecialchars($outward['dest_warehouse_name']) ?>
                        </button>
                    </div>
                </div>
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
<!-- 3. MODAL: One-Variant-QR Verification Workstation -->
<!-- ========================================================================================= -->
<div class="modal fade" id="modalPieceQrViewer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header py-2.5 px-3 bg-dark text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="fs-5">📦</span>
                    <h6 class="modal-title fw-bold mb-0 text-white">One-Variant-QR Verification Workstation</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-2.5 py-1.5 font-monospace">WH: <?= htmlspecialchars($outward['dest_warehouse_name']) ?> (<?= htmlspecialchars($outward['dest_warehouse_code']) ?>)</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4 bg-light">
                <!-- Top Section: Big Variant QR Preview -->
                <div class="row g-3 mb-3 bg-white p-3 rounded-3 shadow-sm mx-0 align-items-center justify-content-center border">
                    <div class="col-12 text-center">
                        <div class="d-inline-block p-2 border rounded-2 bg-white shadow-sm">
                            <div id="modalPieceQrCanvas" class="d-flex align-items-center justify-content-center" style="width: 150px; height: 150px;"></div>
                        </div>
                        <div class="fw-bold font-monospace fs-5 text-primary mt-1" id="modalCurrentPieceTag">—</div>
                    </div>
                </div>

                <!-- Product Attributes & Specifications Card -->
                <div class="card border rounded-3 p-3 bg-white shadow-sm mb-3">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-0" id="modalProductName">—</h5>
                            <div class="font-monospace small text-muted mt-0.5">
                                Product Code: <span class="fw-bold text-dark" id="modalSkuCode">—</span> · Tag: <span class="fw-bold text-primary font-monospace" id="modalVariantTag">—</span>
                            </div>
                        </div>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" id="modalCategoryBadge">Finished Garment</span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="p-2 border rounded-2 bg-light text-center">
                                <div class="small text-muted" style="font-size: 0.7rem;">SIZE</div>
                                <div class="fw-bold text-dark font-monospace" id="modalSizeText">—</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 border rounded-2 bg-light text-center">
                                <div class="small text-muted" style="font-size: 0.7rem;">COLOR</div>
                                <div class="fw-bold text-success font-monospace" id="modalColorText">—</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 border rounded-2 bg-light text-center">
                                <div class="small text-muted" style="font-size: 0.7rem;">MRP</div>
                                <div class="fw-bold text-dark font-monospace" id="modalMrpText">₹0.00</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 border rounded-2 bg-light text-center">
                                <div class="small text-muted" style="font-size: 0.7rem;">UNIT / DISC PRICE</div>
                                <div class="fw-bold text-primary font-monospace" id="modalPriceText">₹0.00</div>
                            </div>
                        </div>
                    </div>

                    <div id="modalExtraAttributesRow" class="d-flex flex-wrap gap-2 mb-3 d-none"></div>

                    <!-- 4 Clear Batch Count Cards -->
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="card border-0 rounded-2 p-2.5 text-center text-white" style="background: #2563eb;">
                                <div class="small text-uppercase fw-semibold" style="font-size: 0.68rem; opacity: 0.85;">Dispatched Expected</div>
                                <div class="fs-4 fw-bold mt-1"><span id="modalStatDispatched">0</span> pcs</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 rounded-2 p-2.5 text-center text-white" style="background: #10b981;">
                                <div class="small text-uppercase fw-semibold" style="font-size: 0.68rem; opacity: 0.85;">Inwarded Good</div>
                                <div class="fs-4 fw-bold mt-1"><span id="modalStatVerified">0</span> pcs</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 rounded-2 p-2.5 text-center text-white" style="background: #ef4444;">
                                <div class="small text-uppercase fw-semibold" style="font-size: 0.68rem; opacity: 0.85;">Damaged Defect</div>
                                <div class="fs-4 fw-bold mt-1"><span id="modalStatDamaged">0</span> pcs</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 rounded-2 p-2.5 text-center text-white" style="background: #f59e0b;">
                                <div class="small text-uppercase fw-semibold" style="font-size: 0.68rem; opacity: 0.85;">Pending Missing</div>
                                <div class="fs-4 fw-bold mt-1"><span id="modalStatPending">0</span> pcs</div>
                            </div>
                        </div>
                    </div>

                    <!-- Interactive + / - Verification Controls -->
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="row g-3">
                            <!-- Good / Intact Verification Controls -->
                            <div class="col-12 col-md-6 border-end <?= $isDamageTransfer ? 'd-none' : '' ?>">
                                <label class="form-label small fw-bold text-success text-uppercase mb-2 d-flex align-items-center gap-1">
                                    <i class="bi bi-check-circle-fill"></i> Verified Good Pieces (Available for Sale)
                                </label>
                                <div class="input-group input-group-lg shadow-sm">
                                    <button type="button" class="btn btn-outline-success fw-bold px-3" onclick="adjustModalCount('verified', -1)">−</button>
                                    <input type="number" id="inputModalVerified" class="form-control text-center fw-bold fs-5 font-monospace border-success" style="background: #f0fdf4;" value="0" min="0" oninput="onModalInputDirect('verified')">
                                    <button type="button" class="btn btn-success fw-bold px-3" onclick="adjustModalCount('verified', 1)">+</button>
                                </div>
                            </div>

                            <!-- Damaged / Defect Controls -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-danger text-uppercase mb-2 d-flex align-items-center gap-1">
                                    <i class="bi bi-slash-circle-fill"></i> <?= $isDamageTransfer ? 'Damaged Pieces (Remain in Quarantine)' : 'Damaged Defect Pieces (Quarantine)' ?>
                                </label>
                                <div class="input-group input-group-lg shadow-sm">
                                    <button type="button" class="btn btn-outline-danger fw-bold px-3" onclick="adjustModalCount('damaged', -1)">−</button>
                                    <input type="number" id="inputModalDamaged" class="form-control text-center fw-bold fs-5 font-monospace border-danger" style="background: #fef2f2;" value="0" min="0" oninput="onModalInputDirect('damaged')">
                                    <button type="button" class="btn btn-danger fw-bold px-3" onclick="adjustModalCount('damaged', 1)">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-white py-2.5 px-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted"><i class="bi bi-shield-check text-success me-1"></i> Real-time synchronized with Consignment Reconcile Table.</span>
                <button type="button" class="btn btn-primary px-4 fw-bold" data-bs-dismiss="modal">
                    <i class="bi bi-check2 me-1"></i> Done & Apply
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================================= -->
<!-- 4. MODAL: Confirm Warehouse Inward -->
<!-- ========================================================================================= -->
<div class="modal fade" id="modalConfirmInwardAcceptance" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4 border-0" style="background: #16a34a;">
                <div>
                    <h5 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <span id="confirmInwardModalTitle">Confirm Warehouse Inward</span>
                    </h5>
                    <div class="small opacity-75 mt-0.5 font-monospace">
                        Batch <?= htmlspecialchars($isDamageTransfer ? ($outward['outward_no'] ?? '') : ($outward['batch_no'] ?? '')) ?>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white align-self-start" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <div class="p-3 border rounded-3 mb-3 d-flex align-items-center gap-3 bg-white">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 bg-success-subtle text-success" style="width: 44px; height: 44px;">
                        <i class="bi bi-building fs-4"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Destination warehouse</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($outward['dest_warehouse_name'] ?? '') ?> (<?= htmlspecialchars($outward['dest_warehouse_code'] ?? '') ?>)</div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-4" id="confirmInwardVerifiedBox">
                        <div class="p-3 rounded-3 text-center border" style="background: #ecfdf5; border-color: #d1fae5 !important;">
                            <div class="small text-uppercase fw-bold text-success mb-1" style="font-size: 0.70rem;">Verified Intact</div>
                            <div class="fs-3 fw-bold text-success" id="confirmInwardVerifiedCount">0</div>
                            <div class="small text-muted" style="font-size: 0.75rem;">pcs credited</div>
                        </div>
                    </div>
                    <div class="col-4" id="confirmInwardDamagedBox">
                        <div class="p-3 rounded-3 text-center border" style="background: #fef2f2; border-color: #fee2e2 !important;">
                            <div class="small text-uppercase fw-bold text-danger mb-1" style="font-size: 0.70rem;">Damaged</div>
                            <div class="fs-3 fw-bold text-danger" id="confirmInwardDamagedCount">0</div>
                            <div class="small text-muted" style="font-size: 0.75rem;">pcs quarantined</div>
                        </div>
                    </div>
                    <div class="col-4" id="confirmInwardMissingBox">
                        <div class="p-3 rounded-3 text-center border" style="background: #fffbeb; border-color: #fef3c7 !important;">
                            <div class="small text-uppercase fw-bold mb-1" style="font-size: 0.70rem; color: #b45309;">Missing</div>
                            <div class="fs-3 fw-bold" style="color: #92400e;" id="confirmInwardMissingCount">0</div>
                            <div class="small text-muted" style="font-size: 0.75rem;">pcs discrepancy</div>
                        </div>
                    </div>
                </div>

                <div class="small text-muted d-flex align-items-start gap-2" id="confirmInwardModalSubtitle">
                    <i class="bi bi-info-circle mt-0.5"></i>
                    <span>Damaged pieces will not be added to sellable stock. Missing pieces will be recorded in the discrepancy ledger.</span>
                </div>
            </div>
            <div class="modal-footer bg-white py-3 px-4 border-top d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal">Review Scans</button>
                <button type="button" class="btn btn-success px-4 fw-bold d-flex align-items-center gap-2" id="btnConfirmInwardSubmit" style="background: #16a34a; border-color: #16a34a;" onclick="confirmInwardModalSubmit()">
                    <i class="bi bi-check-circle"></i> Confirm &amp; Credit Stock
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include QR Code Library -->
<script src="<?= \App\Core\Controller::url('/assets/js/qrcode.min.js') ?>"></script>

<!-- Interactive Logic for Image 2 & Image 3 -->
<script>
// Global State
const isDamageTransfer = <?= json_encode($isDamageTransfer) ?>;
let pieceData = [];
let scanMode = isDamageTransfer ? 'damaged' : 'good';
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
    renderQrToCanvas('batchQrCanvas', <?= json_encode($masterOutwardQrCode ?? '') ?>, 150, 150);
    document.addEventListener('keydown', function (event) {
        if (!event.altKey || event.ctrlKey || event.metaKey) return;
        const key = event.key.toLowerCase();
        if (key === 'g') {
            event.preventDefault();
            setScanMode('good');
        } else if (key === 'd') {
            event.preventDefault();
            setScanMode('damaged');
        }
    });

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

// Bulk scan all items in the outward consignment
function bulkScanOutwardMaster(scannedLabel = '') {
    const feedback = document.getElementById('scanFeedbackAlert');
    const label = scannedLabel || <?= json_encode($masterOutwardQrCode ?? '') ?>;
    let updatedLines = 0;
    let addedPieces = 0;
    document.querySelectorAll('#tableReconcile tbody tr').forEach(row => {
        const verifiedInput = row.querySelector('.input-verified');
        const damagedInput = row.querySelector('.input-damaged');
        if (!verifiedInput || !damagedInput) return;
        const targetInput = (isDamageTransfer || scanMode !== 'good') ? damagedInput : verifiedInput;
        const cur = parseInt(targetInput.value, 10) || 0;
        const verified = parseInt(verifiedInput.value, 10) || 0;
        const damaged = parseInt(damagedInput.value, 10) || 0;
        const max = parseInt(verifiedInput.getAttribute('max'), 10) || 0;
        const remaining = Math.max(0, max - (verified + damaged));
        if (remaining > 0) {
            targetInput.value = cur + remaining;
            updatedLines++;
            addedPieces += remaining;
        }
    });
    recalculateTotals();
    if (updatedLines > 0) {
        feedback.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Outward QR (${escapeHtml(label)}) scanned — ${updatedLines} line(s) / ${addedPieces} pcs scanned completely!</span>`;
        playSound('success');
    } else {
        feedback.innerHTML = `<span class="text-info fw-semibold"><i class="bi bi-info-circle me-1"></i> All items in outward ${escapeHtml(label)} are already scanned.</span>`;
    }
}

// Process Live Scan
function processManualScan() {
    const input = document.getElementById('inputQrScanner');
    const val = (input.value || '').trim();
    const feedback = document.getElementById('scanFeedbackAlert');

    if (!val) {
        feedback.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i> Please scan or type a piece tag, Product Code, or QR code.</span>';
        playSound('error');
        return;
    }

    // Match outward master QR (scans whole outward list completely)
    const scanCode = val.toUpperCase();
    const outwardMasterCodes = [
        <?= json_encode(strtoupper(trim((string)($outward['outward_no'] ?? '')))) ?>,
        <?= json_encode(strtoupper(trim((string)($outward['batch_no'] ?? '')))) ?>
    ].filter(Boolean);
    if (outwardMasterCodes.includes(scanCode)) {
        bulkScanOutwardMaster(val);
        input.value = '';
        input.focus();
        return;
    }

    // Match in reconcile table
    const matchedRow = Array.from(document.querySelectorAll('#tableReconcile tbody tr')).find(row => {
        return [row.dataset.tagCode, row.dataset.productCode, row.dataset.variantId]
            .some(code => code && code.toUpperCase() === scanCode);
    });
    if (!matchedRow) {
        feedback.innerHTML = `<span class="text-danger fw-semibold"><i class="bi bi-x-circle me-1"></i> '${escapeHtml(val)}' is not an item in this outward consignment.</span>`;
        playSound('error');
        return;
    }

    const verifiedInput = matchedRow.querySelector('.input-verified');
    const damagedInput = matchedRow.querySelector('.input-damaged');
    const targetInput = scanMode === 'good' ? verifiedInput : damagedInput;
    const cur = parseInt(targetInput.value, 10) || 0;
    const verified = parseInt(verifiedInput.value, 10) || 0;
    const damaged = parseInt(damagedInput.value, 10) || 0;
    const max = parseInt(verifiedInput.getAttribute('max'), 10) || 0;
    if (verified + damaged >= max) {
        feedback.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> All dispatched pieces for ${escapeHtml(matchedRow.dataset.productName)} have already been accounted for.</span>`;
        playSound('error');
        input.value = '';
        return;
    }

    targetInput.value = cur + 1;
    recalculateTotals();
    const scanResult = scanMode === 'good' ? '+1 good' : '+1 damaged';
    feedback.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Scanned ${escapeHtml(matchedRow.dataset.productName)} (${escapeHtml(matchedRow.dataset.productCode)}). ${scanResult}.</span>`;
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

        const vVal = isDamageTransfer ? 0 : (parseInt(vInput.value) || 0);
        if (isDamageTransfer) vInput.value = 0;
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
    const damageReceivedTotal = newVerifiedTotal + newDamagedTotal;
    const newPendingTotal = Math.max(0, totalDispatchedConst - damageReceivedTotal);
    const newMatch = totalDispatchedConst > 0
        ? Math.round(((isDamageTransfer ? damageReceivedTotal : newVerifiedTotal) / totalDispatchedConst) * 100)
        : 0;

    document.getElementById('statVerified').textContent = isDamageTransfer ? damageReceivedTotal : newVerifiedTotal;
    document.getElementById('statDamaged').textContent = newDamagedTotal;
    document.getElementById('statQuarantine').textContent = newDamagedTotal;
    document.getElementById('statPending').textContent = newPendingTotal;
    document.getElementById('statMatch').textContent = newMatch;

    // Update CTA button & banner
    document.getElementById('btnIncrementalQty').textContent = isDamageTransfer ? incDamaged : incVerified;
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

    document.getElementById('bannerCreditedText').textContent = isDamageTransfer ? damageReceivedTotal : newVerifiedTotal;
    document.getElementById('bannerPendingText').textContent = newPendingTotal;
    document.getElementById('bannerPendingBadge').textContent = `${newPendingTotal} pcs pending`;
}

// Validate Submission
let inwardConfirmed = false;

function validateCreditSubmission(e) {
    if (inwardConfirmed) {
        return true;
    }

    const vQty = parseInt(document.getElementById('submitVerifiedQty').value) || 0;
    const dQty = parseInt(document.getElementById('submitDamagedQty').value) || 0;

    if (vQty <= 0 && dQty <= 0) {
        e.preventDefault();
        const feedback = document.getElementById('scanFeedbackAlert');
        if (feedback) {
            feedback.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle me-1"></i>Please scan or enter at least 1 verified or damaged piece before updating inventory stock.</span>';
        }
        return false;
    }

    e.preventDefault();
    const titleEl = document.getElementById('confirmInwardModalTitle');
    const vBox = document.getElementById('confirmInwardVerifiedBox');
    const dBox = document.getElementById('confirmInwardDamagedBox');
    const mBox = document.getElementById('confirmInwardMissingBox');
    const vCountEl = document.getElementById('confirmInwardVerifiedCount');
    const dCountEl = document.getElementById('confirmInwardDamagedCount');
    const mCountEl = document.getElementById('confirmInwardMissingCount');
    const pendingTotal = parseInt(document.getElementById('statPending')?.textContent?.replace(/,/g, '') || '0', 10) || 0;

    if (isDamageTransfer) {
        if (titleEl) titleEl.textContent = 'Confirm Damage Inward';
        if (vBox) vBox.classList.add('d-none');
        if (mBox) mBox.classList.add('d-none');
        if (dBox) dBox.className = 'col-12';
        if (dCountEl) dCountEl.textContent = `${vQty + dQty}`;
    } else {
        if (titleEl) titleEl.textContent = 'Confirm Warehouse Inward';
        if (vBox) vBox.classList.remove('d-none');
        if (mBox) mBox.classList.remove('d-none');
        if (dBox) dBox.className = 'col-4';
        if (vCountEl) vCountEl.textContent = `${vQty}`;
        if (dCountEl) dCountEl.textContent = `${dQty}`;
        if (mCountEl) mCountEl.textContent = `${pendingTotal}`;
    }

    const modalEl = document.getElementById('modalConfirmInwardAcceptance');
    if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    return false;
}

function confirmInwardModalSubmit() {
    inwardConfirmed = true;
    const modalEl = document.getElementById('modalConfirmInwardAcceptance');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
    }
    const submitBtn = document.getElementById('btnSubmitStock');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Crediting Stock to Inventory...';
    }
    const form = document.getElementById('formCreditStock');
    if (form) form.submit();
}

// One-Variant-QR Verification Workstation Logic
function openPieceModal(outwardId, variantId) {
    activeModalOutwardId = outwardId;
    activeModalVariantId = variantId;

    const row = document.getElementById(`rowVariant-${variantId}`);
    if (!row) return;

    const productName = row.dataset.productName || 'Garment';
    const productCode = row.dataset.productCode || '—';
    const tagCode = row.dataset.tagCode || productCode;
    const size = row.dataset.size || '—';
    const color = row.dataset.color || '—';
    const mrp = parseFloat(row.dataset.mrp) || 0;
    const price = parseFloat(row.dataset.price) || 0;
    const category = row.dataset.category || 'Finished Garment';

    const vInput = row.querySelector('.input-verified');
    const dInput = row.querySelector('.input-damaged');
    const curV = parseInt(vInput ? vInput.value : 0, 10) || 0;
    const curD = parseInt(dInput ? dInput.value : 0, 10) || 0;

    // Populate modal texts
    const nameEl = document.getElementById('modalProductName');
    if (nameEl) nameEl.textContent = productName;
    const skuEl = document.getElementById('modalSkuCode');
    if (skuEl) skuEl.textContent = productCode;
    const tagEl = document.getElementById('modalVariantTag');
    if (tagEl) tagEl.textContent = tagCode;
    const currentPieceTagEl = document.getElementById('modalCurrentPieceTag');
    if (currentPieceTagEl) currentPieceTagEl.textContent = tagCode;
    const catEl = document.getElementById('modalCategoryBadge');
    if (catEl) catEl.textContent = category;
    const sizeEl = document.getElementById('modalSizeText');
    if (sizeEl) sizeEl.textContent = size;
    const colEl = document.getElementById('modalColorText');
    if (colEl) colEl.textContent = color;
    const mrpEl = document.getElementById('modalMrpText');
    if (mrpEl) mrpEl.textContent = '₹' + mrp.toFixed(2);
    const prEl = document.getElementById('modalPriceText');
    if (prEl) prEl.textContent = '₹' + price.toFixed(2);

    const extraAttrsRow = document.getElementById('modalExtraAttributesRow');
    if (extraAttrsRow) {
        let extraAttrs = {};
        try {
            extraAttrs = JSON.parse(row.dataset.attributes || '{}') || {};
        } catch (e) {
            extraAttrs = {};
        }
        const entries = Object.entries(extraAttrs);
        extraAttrsRow.innerHTML = '';
        if (entries.length > 0) {
            entries.forEach(([k, v]) => {
                const badge = document.createElement('span');
                badge.className = 'badge bg-light text-dark border px-2 py-1';
                badge.textContent = `${k}: ${v}`;
                extraAttrsRow.appendChild(badge);
            });
            extraAttrsRow.classList.remove('d-none');
        } else {
            extraAttrsRow.classList.add('d-none');
        }
    }

    // Label generation link
    const labelLink = document.getElementById('modalLabelGenLink');
    if (labelLink) {
        labelLink.href = `<?= \App\Core\Controller::url('/m/label_generation') ?>?variant_id=${variantId}&outward_id=${outwardId}`;
    }

    // Render single static QR canvas for this variant
    renderQrToCanvas('modalPieceQrCanvas', tagCode, 140, 140);

    // Set input values
    const modalVInput = document.getElementById('inputModalVerified');
    const modalDInput = document.getElementById('inputModalDamaged');
    if (modalVInput) modalVInput.value = curV;
    if (modalDInput) modalDInput.value = curD;

    updateModalCountCards(variantId);

    const modal = new bootstrap.Modal(document.getElementById('modalPieceQrViewer'));
    modal.show();
}

function updateModalCountCards(variantId) {
    const row = document.getElementById(`rowVariant-${variantId}`);
    if (!row) return;

    const dispatched = parseInt(row.dataset.dispatchedQty, 10) || 0;
    const prevCredited = parseInt(row.dataset.creditedQty, 10) || 0;
    const prevDamaged = parseInt(row.dataset.damagedQty, 10) || 0;

    const modalVInput = document.getElementById('inputModalVerified');
    const modalDInput = document.getElementById('inputModalDamaged');
    const incVerified = parseInt(modalVInput ? modalVInput.value : 0, 10) || 0;
    const incDamaged = parseInt(modalDInput ? modalDInput.value : 0, 10) || 0;

    const totalGood = prevCredited + incVerified;
    const totalDam = prevDamaged + incDamaged;
    const pending = Math.max(0, dispatched - totalGood - totalDam);

    const statDisp = document.getElementById('modalStatDispatched');
    if (statDisp) statDisp.textContent = dispatched;
    const statVer = document.getElementById('modalStatVerified');
    if (statVer) statVer.textContent = totalGood;
    const statDam = document.getElementById('modalStatDamaged');
    if (statDam) statDam.textContent = totalDam;
    const statPend = document.getElementById('modalStatPending');
    if (statPend) statPend.textContent = pending;
}

function syncModalToRow() {
    const row = document.getElementById(`rowVariant-${activeModalVariantId}`);
    if (!row) return;

    const modalVInput = document.getElementById('inputModalVerified');
    const modalDInput = document.getElementById('inputModalDamaged');
    const vInput = row.querySelector('.input-verified');
    const dInput = row.querySelector('.input-damaged');

    if (vInput && modalVInput) vInput.value = modalVInput.value;
    if (dInput && modalDInput) dInput.value = modalDInput.value;

    recalculateTotals();
    updateModalCountCards(activeModalVariantId);
}

function adjustModalCount(type, delta) {
    if (isDamageTransfer && type !== 'damaged') return;
    const row = document.getElementById(`rowVariant-${activeModalVariantId}`);
    if (!row) return;

    const maxRem = parseInt(row.querySelector('.input-verified').getAttribute('max'), 10) || 0;
    const modalVInput = document.getElementById('inputModalVerified');
    const modalDInput = document.getElementById('inputModalDamaged');

    let curV = parseInt(modalVInput ? modalVInput.value : 0, 10) || 0;
    let curD = parseInt(modalDInput ? modalDInput.value : 0, 10) || 0;

    if (type === 'verified') {
        const nextV = curV + delta;
        if (nextV < 0) return;
        if (nextV + curD > maxRem) {
            playSound('error');
            return;
        }
        modalVInput.value = nextV;
    } else {
        const nextD = curD + delta;
        if (nextD < 0) return;
        if (curV + nextD > maxRem) {
            playSound('error');
            return;
        }
        modalDInput.value = nextD;
    }

    playSound('success');
    syncModalToRow();
}

function onModalInputDirect(type) {
    if (isDamageTransfer && type !== 'damaged') return;
    const row = document.getElementById(`rowVariant-${activeModalVariantId}`);
    if (!row) return;

    const maxRem = parseInt(row.querySelector('.input-verified').getAttribute('max'), 10) || 0;
    const modalVInput = document.getElementById('inputModalVerified');
    const modalDInput = document.getElementById('inputModalDamaged');

    let curV = parseInt(modalVInput ? modalVInput.value : 0, 10) || 0;
    let curD = parseInt(modalDInput ? modalDInput.value : 0, 10) || 0;

    if (curV < 0) { curV = 0; if (modalVInput) modalVInput.value = 0; }
    if (curD < 0) { curD = 0; if (modalDInput) modalDInput.value = 0; }

    if (curV + curD > maxRem) {
        if (type === 'verified') {
            curV = Math.max(0, maxRem - curD);
            if (modalVInput) modalVInput.value = curV;
        } else {
            curD = Math.max(0, maxRem - curV);
            if (modalDInput) modalDInput.value = curD;
        }
        playSound('error');
    }

    syncModalToRow();
}

function verifyAllRemainingModal() {
    if (isDamageTransfer) return;
    const row = document.getElementById(`rowVariant-${activeModalVariantId}`);
    if (!row) return;

    const maxRem = parseInt(row.querySelector('.input-verified').getAttribute('max'), 10) || 0;
    const modalVInput = document.getElementById('inputModalVerified');
    const modalDInput = document.getElementById('inputModalDamaged');

    const curD = parseInt(modalDInput ? modalDInput.value : 0, 10) || 0;
    if (modalVInput) modalVInput.value = Math.max(0, maxRem - curD);

    playSound('success');
    syncModalToRow();
}

function markAllRemainingDamagedModal() {
    const row = document.getElementById(`rowVariant-${activeModalVariantId}`);
    if (!row) return;

    const maxRem = parseInt(row.querySelector('.input-verified').getAttribute('max'), 10) || 0;
    const modalVInput = document.getElementById('inputModalVerified');
    const modalDInput = document.getElementById('inputModalDamaged');

    const curV = parseInt(modalVInput ? modalVInput.value : 0, 10) || 0;
    if (modalDInput) modalDInput.value = Math.max(0, maxRem - curV);

    playSound('success');
    syncModalToRow();
}

function inwardNextPiece() {
    adjustModalCount(scanMode === 'good' ? 'verified' : 'damaged', 1);
}

function setScanMode(mode) {
    if (isDamageTransfer) mode = 'damaged';
    scanMode = mode;
    const btnGood = document.getElementById('btnModeGood');
    const btnDamaged = document.getElementById('btnModeDamaged');
    const btnNext = document.getElementById('btnInwardNextPiece');

    if (mode === 'good') {
        if (btnGood) btnGood.className = 'btn btn-success fw-bold px-3 py-2 flex-grow-1 shadow-sm d-flex align-items-center justify-content-center gap-2';
        if (btnDamaged) btnDamaged.className = 'btn btn-outline-danger fw-semibold px-3 py-2 flex-grow-1 d-flex align-items-center justify-content-center gap-2';
        if (btnNext) {
            btnNext.className = 'btn btn-success btn-lg w-100 fw-bold py-2.5 shadow-sm d-flex align-items-center justify-content-center gap-2';
            btnNext.innerHTML = '<i class="bi bi-check2-circle fs-5"></i> Inward Next Piece (+1 Good)';
        }
    } else {
        if (btnDamaged) btnDamaged.className = 'btn btn-danger fw-bold px-3 py-2 flex-grow-1 shadow-sm d-flex align-items-center justify-content-center gap-2';
        if (btnGood) btnGood.className = `btn btn-outline-success fw-semibold px-3 py-2 flex-grow-1 d-flex align-items-center justify-content-center gap-2${isDamageTransfer ? ' d-none' : ''}`;
        if (btnNext) {
            btnNext.className = 'btn btn-danger btn-lg w-100 fw-bold py-2.5 shadow-sm d-flex align-items-center justify-content-center gap-2';
            btnNext.innerHTML = '<i class="bi bi-slash-circle fs-5"></i> Inward Next Piece (+1 Damaged)';
        }
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (character) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
}
</script>
