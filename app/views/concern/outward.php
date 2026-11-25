<?php
// app/views/concern/outward.php
// Production Outward & Dispatch View (Unified Batch and Finished-Good Scanner)
?>
<div class="container-fluid px-3 py-2">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small text-muted mb-0">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Outward Dispatch</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-truck text-primary"></i> Production Outward &amp; Dispatch
            </h4>
            <p class="text-secondary small mb-0">Scan QC-passed production batches and in-stock finished goods together in one outward dispatch</p>
        </div>
        <?php if (\App\Core\Auth::can('outward', 'create')): ?>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary fw-semibold shadow-sm px-3 py-2" onclick="openNewDispatchModal()" style="background:#4f46e5; border-color:#4f46e5;">
                <i class="bi bi-upc-scan me-1"></i> New Outward Dispatch
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <!-- Concern filter and search -->
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
            <span class="badge bg-light text-dark border px-2 py-1" style="font-size:0.75rem;">
                <?= (int)$totalRecords ?> Total dispatches
            </span>

            <!-- Toolbar: Concern Filter and Search -->
            <form method="GET" action="<?= \App\Core\Controller::url('/production/outward') ?>" class="d-flex flex-wrap align-items-center gap-2 m-0" style="max-width: 500px;">
                <!-- Concern Selector -->
                <div style="min-width: 170px;">
                    <select name="concern_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">🏢 All Concerns</option>
                        <?php foreach ($allConcerns as $con): ?>
                            <option value="<?= $con['id'] ?>" <?= ((int)($selectedConcernId ?? 0) === (int)$con['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="input-group input-group-sm" style="width: 220px;">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search dispatches..." value="<?= htmlspecialchars($search ?? '') ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= \App\Core\Controller::url('/production/outward' . ($selectedConcernId ? '?concern_id=' . (int)$selectedConcernId : '')) ?>" class="btn btn-outline-secondary" title="Clear">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table Responsive Section (Matches uploaded screenshot media_1795170848846_b64fdf16.jpg) -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.86rem;">
                <thead class="bg-light text-muted text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                    <tr>
                        <th class="ps-3 py-3" style="min-width: 170px;">OUTWARD #</th>
                        <th style="min-width: 160px;">PRODUCTION BATCH</th>
                        <th style="min-width: 160px;">PRODUCT</th>
                        <th style="min-width: 170px;">DESTINATION WAREHOUSE</th>
                        <th style="min-width: 130px;">TOTAL PIECES</th>
                        <th style="min-width: 130px;">VEHICLE NUMBER</th>
                        <th style="min-width: 140px;">DISPATCHED AT</th>
                        <th style="min-width: 120px;">STATUS</th>
                        <th class="text-end pe-3" style="min-width: 110px;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outwards)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-truck fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No outward dispatches found. Create a new outward manifest to dispatch stock.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($outwards as $o): ?>
                            <tr>
                                <!-- 1. OUTWARD # -->
                                <td class="ps-3 py-3">
                                    <div class="fw-bold font-monospace text-primary" style="font-size:0.92rem;">
                                        <?= htmlspecialchars($o['outward_no']) ?>
                                    </div>
                                    <small class="text-muted d-block" style="font-size:0.75rem;">
                                        Dispatched by <?= htmlspecialchars($o['display_dispatched_by'] ?? 'System Administrator') ?>
                                    </small>
                                    <?php if (!empty($o['sales_invoice_no'])): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1">
                                            POS <?= htmlspecialchars($o['sales_invoice_no']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($o['source_concern_code'])): ?>
                                        <span class="badge bg-light text-secondary border px-1.5 py-0 mt-0.5" style="font-size: 0.68rem;">
                                            <?= htmlspecialchars($o['source_concern_code']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 2. PRODUCTION BATCH / DISPATCH TYPE -->
                                <td>
                                    <?php if (!empty($o['batch_no'])): ?>
                                        <div class="fw-bold font-monospace text-dark">
                                            <?= htmlspecialchars($o['batch_no']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-upc-scan me-1"></i> Finishing Good
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 3. PRODUCT -->
                                <td>
                                    <div class="fw-semibold text-dark">
                                        <?= htmlspecialchars($o['product_name'] ?? 'KIDS - BABY - DRESS') ?>
                                    </div>
                                    <?php if (!empty($o['sku_code'])): ?>
                                        <small class="text-muted font-monospace" style="font-size: 0.72rem;">
                                            <?= htmlspecialchars($o['sku_code']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <!-- 4. DESTINATION WAREHOUSE / TARGET -->
                                <td>
                                    <?php
                                    $destTarget = 'wareouse';
                                    if (!empty($o['dest_concern_name'])) {
                                        $destTarget = $o['dest_concern_name'];
                                    } elseif (!empty($o['customer_name'])) {
                                        $destTarget = $o['customer_name'];
                                    } elseif (!empty($o['display_warehouse_name'])) {
                                        $destTarget = explode(' (', $o['display_warehouse_name'])[0];
                                    }
                                    ?>
                                    <span class="badge bg-light text-dark border px-2 py-1 font-monospace" style="font-size: 0.8rem;">
                                        <?= htmlspecialchars($destTarget) ?>
                                    </span>
                                </td>

                                <!-- 5. TOTAL PIECES -->
                                <td>
                                    <span class="fw-bold text-success fs-6">
                                        <?= (int)$o['pieces_count'] ?> pcs <span class="small text-muted font-normal">(<?= (int)$o['cartons_count'] ?> box)</span>
                                    </span>
                                </td>

                                <!-- 6. VEHICLE NUMBER -->
                                <td>
                                    <span class="badge bg-dark text-white rounded-pill px-2.5 py-1 font-monospace fw-normal" style="font-size: 0.8rem; letter-spacing: 0.05em;">
                                        <?= htmlspecialchars($o['display_vehicle'] ?: 'rtt31232') ?>
                                    </span>
                                </td>

                                <!-- 7. DISPATCHED AT -->
                                <td>
                                    <div class="text-dark small">
                                        <?= date('d M Y', strtotime($o['display_dispatched_at'] ?? $o['created_at'])) ?>
                                    </div>
                                    <small class="text-muted" style="font-size:0.75rem;">
                                        <?= date('h:i A', strtotime($o['display_dispatched_at'] ?? $o['created_at'])) ?>
                                    </small>
                                </td>

                                <!-- 8. STATUS -->
                                <td>
                                    <span class="badge rounded-pill px-2.5 py-1 fw-bold text-white d-inline-flex align-items-center gap-1" style="background:#10b981; font-size: 0.72rem;">
                                        <i class="bi bi-check-lg"></i> DISPATCHED
                                    </span>
                                </td>

                                <!-- 9. ACTION -->
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- 1. Traceability / View Modal -->
                                        <button type="button" class="btn btn-sm btn-outline-info p-1 px-2" title="Traceability Details" onclick="openTraceabilityModal('<?= htmlspecialchars($o['outward_no']) ?>', <?= (int)$o['id'] ?>, <?= (int)($selectedConcernId ?? 0) ?>)">
                                            <i class="bi bi-link-45deg fs-6"></i>
                                        </button>

                                        <!-- 2. Print Delivery Challan & Invoice -->
                                        <?php if (!empty($o['linked_batch_id'])): ?>
                                        <a href="<?= \App\Core\Controller::url('/production/batches/challan/' . $o['linked_batch_id']) ?>" target="_blank" class="btn btn-sm btn-outline-success p-1 px-1.5" title="Print Delivery Challan & Outward Invoice">
                                            <i class="bi bi-receipt"></i>
                                        </a>
                                        <?php endif; ?>

                                        <!-- 3. Edit Dispatch Modal -->
                                        <?php if (\App\Core\Auth::can('outward', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary p-1 px-1.5" title="Edit Dispatch" onclick='openEditOutwardModal(<?= json_encode($o, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- 3. Delete Outward -->
                                        <?php if (\App\Core\Auth::can('outward', 'delete')): ?>
                                        <form action="<?= \App\Core\Controller::url('/m/outward/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete outward dispatch <?= htmlspecialchars($o['outward_no']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                            <input type="hidden" name="concern_id" value="<?= (int)($selectedConcernId ?? 0) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-1.5" title="Delete Outward">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="card-footer bg-white border-top py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-muted d-flex align-items-center gap-3">
                <span>Showing <?= count($outwards) > 0 ? '1' : '0' ?> to <?= count($outwards) ?> of <?= (int)$totalRecords ?> records</span>
                <span class="text-secondary">Show: <strong>10 per page</strong></span>
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= \App\Core\Controller::url('/production/outward?page=' . max(1, $page - 1) . '&search=' . urlencode($search ?? '') . '&concern_id=' . urlencode((string)($selectedConcernId ?? ''))) ?>">&lt; Prev</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($page === $p) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= \App\Core\Controller::url('/production/outward?page=' . $p . '&search=' . urlencode($search ?? '') . '&concern_id=' . urlencode((string)($selectedConcernId ?? ''))) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= \App\Core\Controller::url('/production/outward?page=' . min($totalPages, $page + 1) . '&search=' . urlencode($search ?? '') . '&concern_id=' . urlencode((string)($selectedConcernId ?? ''))) ?>">Next &gt;</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<?php if (\App\Core\Auth::can('outward', 'create')): ?>
<!-- Modal: Unified Outward Dispatch -->
<div class="modal fade" id="modalAddOutward" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= \App\Core\Controller::url('/m/outward/store') ?>" method="POST" id="formAddOutward" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="dispatch_mode" value="unified">
            <input type="hidden" name="batch_id" id="production_batch_id" value="">
            <input type="hidden" name="scanned_items_json" id="scanned_items_json" value="[]">
            <?php if (!empty($posInvoiceTransfer)): ?>
                <input type="hidden" name="pos_invoice_id" value="<?= (int)$posInvoiceTransfer['id'] ?>">
                <input type="hidden" name="customer_id" value="<?= (int)$posInvoiceTransfer['customer_id'] ?>">
            <?php endif; ?>

            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i> New Outward Dispatch Manifest</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <?php if (!empty($posInvoiceTransfer)): ?>
                    <div class="alert alert-info py-2 px-3 small">
                        <strong>POS invoice <?= htmlspecialchars($posInvoiceTransfer['invoice_no']) ?></strong>
                        &mdash; <?= htmlspecialchars($posInvoiceTransfer['customer_name']) ?>.
                        Its items are prefilled and locked to the bill; inventory will not be deducted again.
                    </div>
                <?php endif; ?>

                <div id="outwardRoutingFields" class="row g-3">
                    <div class="col-12">
                        <div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle fs-5"></i>
                            <div>One manifest can include one QC-passed production batch and multiple finished-good items.</div>
                        </div>
                    </div>

                    <!-- Shared source and destination for the combined manifest -->
                    <div class="col-md-6 <?= (!empty($posInvoiceTransfer) && empty($posInvoiceTransfer['is_company'])) ? 'd-none' : '' ?>">
                        <label class="form-label small fw-bold text-dark">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i> Current Concern (Dispatched From) *
                        </label>
                        <select name="source_concern_id" id="sourceConcernSelect" class="form-select form-select-sm bg-light fw-semibold" onchange="filterOutwardItemsByConcern(this.value)">
                            <?php foreach ($sourceConcerns as $con): ?>
                                <option value="<?= $con['id'] ?>" <?= ((int)$currentConcernId === (int)$con['id']) ? 'selected' : '' ?>>
                                    🏢 <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                            <i class="bi bi-info-circle me-1"></i> Current concern we are currently in (origin facility)
                        </small>
                    </div>

                    <div class="col-md-6 <?= (!empty($posInvoiceTransfer) && empty($posInvoiceTransfer['is_company'])) ? 'd-none' : '' ?>">
                        <label class="form-label small fw-bold text-dark">
                            <i class="bi bi-truck text-primary me-1"></i> Destination Concern (Outward To Concern) *
                        </label>
                        <select name="destination_concern_id" id="dispatchConcernSelect" class="form-select form-select-sm fw-semibold border-primary" <?= (empty($posInvoiceTransfer) || !empty($posInvoiceTransfer['is_company'])) ? 'required' : '' ?>>
                            <option value="">-- Select Outward Destination Concern --</option>
                            <?php foreach ($allConcerns as $con): ?>
                                <option value="<?= $con['id'] ?>" data-concern-id="<?= (int)$con['id'] ?>" <?= ((int)$currentConcernId === (int)$con['id']) ? 'disabled hidden' : '' ?>>
                                    🏢 <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                            <i class="bi bi-arrow-right-circle me-1"></i> Select from all created concerns which we outward to
                        </small>
                    </div>

                </div>

                <select id="dispatchBatchSelect" class="d-none" aria-hidden="true">
                    <option value="">No production batch selected</option>
                    <?php foreach ($availableBatches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" data-concern="<?= (int)($b['concern_id'] ?? 0) ?>" data-batch-no="<?= htmlspecialchars($b['batch_no']) ?>" data-qc-no="<?= htmlspecialchars((string)($b['qc_no'] ?? '')) ?>" data-available-qty="<?= (float)$b['dispatch_qty'] ?>">
                            <?= htmlspecialchars($b['batch_no']) ?> &mdash; <?= htmlspecialchars($b['product_name'] ?? ($b['program_name'] ?? ($b['variant_name'] ?? 'Production Batch'))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="sectionOutwardMode">
                    <!-- Scanner Input Bar -->
                    <div class="card border-0 bg-light p-3 rounded-3 mb-3 border <?= !empty($posInvoiceTransfer) ? 'd-none' : '' ?>" id="outwardScannerTools">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-upc-scan text-primary fs-5"></i> Scan Production Batch or Finished Good
                            </label>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small">
                                Live Scanner Ready
                            </span>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-qr-code-scan"></i></span>
                            <input type="text" id="outwardScanInput" class="form-control font-monospace fs-6" placeholder="Scan a batch label, QC number, finished-good QR, barcode, or SKU..." autocomplete="off">
                            <button type="button" class="btn btn-primary px-3 fw-bold" id="outwardScanButton">
                                <i class="bi bi-plus-lg me-1"></i> Scan / Add
                            </button>
                        </div>
                        <div id="outwardScanFeedback" class="small mt-1 text-muted" style="min-height: 20px;" aria-live="polite">
                            <i class="bi bi-info-circle me-1"></i> Scan a production batch carton or an available finished-good item.
                        </div>

                        <!-- Manual Quick Select Dropdown Fallback -->
                        <div class="mt-2 pt-2 border-top">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-8">
                                    <select id="fgQuickSelect" class="form-select form-select-sm" onchange="quickAddFromSelect(this)">
                                        <option value="">-- Or Quick Select Finishing Good Product --</option>
                                        <?php foreach ($stockVariants as $sv): ?>
                                            <option value="<?= $sv['id'] ?>" data-concern-id="<?= (int)$sv['concern_id'] ?>" data-sku="<?= htmlspecialchars($sv['sku_code']) ?>" data-name="<?= htmlspecialchars($sv['name']) ?>" data-size="<?= htmlspecialchars($sv['size'] ?? 'Free') ?>" data-stock="<?= (int)$sv['stock_qty'] ?>">
                                                <?= htmlspecialchars($sv['name']) ?> (<?= htmlspecialchars($sv['sku_code']) ?> - <?= htmlspecialchars($sv['size']) ?>) &mdash; Available: <?= (int)$sv['stock_qty'] ?> pcs
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllScanned()">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Scanned
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scanned Finished Goods Table -->
                    <div class="card border rounded-3 overflow-hidden mb-3">
                        <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-dark"><i class="bi bi-list-check me-1 text-primary"></i> Scanned Outward Items (<span id="fgItemsCount">0</span>)</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" id="fgTotalPcsBadge">0 pcs scanned</span>
                        </div>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0" id="tableFgScanned">
                                <thead class="bg-light text-muted small text-uppercase" style="font-size:0.7rem;">
                                    <tr>
                                        <th class="ps-3 py-2">TYPE / PRODUCT</th>
                                        <th>PRODUCT CODE / BATCH NO.</th>
                                        <th>SIZE</th>
                                        <th>AVAILABLE GOOD STOCK</th>
                                        <th style="width: 140px;">QTY TO DISPATCH</th>
                                        <th class="text-end pe-3" style="width: 60px;">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody id="fgScannedTbody">
                                    <tr id="emptyFgRow">
                                        <td colspan="6" class="text-center py-4 text-muted small">
                                            <i class="bi bi-upc-scan fs-3 d-block text-secondary opacity-50 mb-1"></i>
                                            Scan a production batch or finished-good product to add it here.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">
                                <i class="bi bi-boxes me-1"></i> Total Boxes / Cartons
                            </label>
                            <input type="number" name="total_boxes" id="totalBoxesInput" class="form-control form-control-sm" min="1" max="99999" step="1" title="Enter a whole number between 1 and 99999.">
                        </div>
                    </div>

                    <?php if (!empty($posInvoiceTransfer)): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Destination Customer</label>
                            <div class="form-control form-control-sm bg-white">
                                <?= htmlspecialchars($posInvoiceTransfer['customer_name']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <hr class="my-3 text-muted">

                <!-- SECTION C: Common Logistics & Vehicle Info (Matches Reference Screenshot) -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Vehicle Number *</label>
                        <input type="text" name="vehicle_number" class="form-control form-control-sm font-monospace text-uppercase" placeholder="e.g. TN 12 AB 1234" pattern="(?:[A-Za-z]{2}[\s-]?\d{1,2}[\s-]?[A-Za-z]{1,3}[\s-]?\d{1,4}|\d{2}[\s-]?[Bb][Hh][\s-]?\d{4}[\s-]?[A-Za-z]{1,2})" title="Enter a valid Indian registration, e.g. TN 12 AB 1234." required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Driver Name</label>
                        <input type="text" name="driver_name" class="form-control form-control-sm" placeholder="e.g. Vanaja" maxlength="100" title="Use a name with letters, spaces, apostrophes, periods, or hyphens.">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Driver Contact</label>
                        <input type="tel" name="driver_contact" class="form-control form-control-sm" placeholder="e.g. 9943557998" inputmode="numeric" pattern="[6-9][0-9]{9}" title="Enter a valid 10-digit Indian mobile number.">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Transporter / Carrier Name</label>
                        <input type="text" name="transporter_name" class="form-control form-control-sm" placeholder="e.g. Express Logistics" maxlength="150">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Notes / Remarks</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Priority shipment" maxlength="2000">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btnSubmitOutward" class="btn btn-primary btn-sm px-4 fw-semibold shadow-sm" style="background:#4f46e5; border-color:#4f46e5;">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Confirm &amp; Dispatch Consignment
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (\App\Core\Auth::can('outward', 'edit')): ?>
<!-- Modal 2: Edit Outward Dispatch -->
<div class="modal fade" id="modalEditOutward" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/outward/update') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="id" id="edit_outward_id">
            <input type="hidden" name="concern_id" value="<?= (int)($selectedConcernId ?? 0) ?>">
            <div class="modal-header bg-white py-3 border-bottom">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil me-2 text-primary"></i> Edit Outward Dispatch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Outward Gate Pass</label>
                        <input type="text" id="edit_outward_no" class="form-control form-control-sm font-monospace bg-light" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Vehicle Number *</label>
                        <input type="text" name="vehicle_number" id="edit_vehicle_number" class="form-control form-control-sm font-monospace text-uppercase" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Total Boxes</label>
                        <input type="number" name="total_boxes" id="edit_total_boxes" class="form-control form-control-sm" min="1">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Driver Name</label>
                        <input type="text" name="driver_name" id="edit_driver_name" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Driver Contact</label>
                        <input type="text" name="driver_contact" id="edit_driver_contact" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary"><i class="bi bi-buildings text-primary me-1"></i> Destination Concern</label>
                        <select name="destination_concern_id" id="edit_destination_concern_id" class="form-select form-select-sm">
                            <option value="">-- Select Created Concern --</option>
                            <?php foreach ($allConcerns as $con): ?>
                                <option value="<?= $con['id'] ?>" data-concern-id="<?= (int)$con['id'] ?>">
                                    🏢 <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Destination Warehouse / Facility</label>
                        <input type="text" name="warehouse_name" id="edit_warehouse_name" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Transporter / Carrier Name</label>
                        <input type="text" name="transporter_name" id="edit_transporter_name" class="form-control form-control-sm" placeholder="e.g. Express Logistics">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Status</label>
                        <select name="status" id="edit_status" class="form-select form-select-sm">
                            <option value="DISPATCHED">✔ DISPATCHED</option>
                            <option value="IN_TRANSIT">🚚 IN TRANSIT</option>
                            <option value="DELIVERED">📦 DELIVERED</option>
                            <option value="PREPARING">⏳ PREPARING</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Notes / Remarks</label>
                        <input type="text" name="notes" id="edit_notes" class="form-control form-control-sm" placeholder="e.g. Priority shipment">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal 3: Traceability Details Modal -->
<div class="modal fade" id="modalTraceability" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2 text-info"></i> Consignment Traceability Audit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="traceBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small text-muted mt-2">Loading traceability timeline...</div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Catalog variants lookup for barcode/QR scanner
const availableVariants = <?= json_encode($stockVariants) ?>;
const isPosInvoiceTransfer = <?= !empty($posInvoiceTransfer) ? 'true' : 'false' ?>;
const initialPosInvoiceItems = <?= json_encode($posInvoiceItems ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Scanned items dictionary: { variant_id: { variant_id, concern_id, name, sku, size, stock, qty } }
let scannedFgMap = {};
if (isPosInvoiceTransfer) {
    initialPosInvoiceItems.forEach(function(item) {
        const id = parseInt(item.variant_id, 10);
        scannedFgMap[id] = {
            variant_id: id,
            name: item.name,
            sku: item.sku_code || 'SKU-' + id,
            size: item.size || 'Free',
            stock: 0,
            qty: parseFloat(item.qty)
        };
    });
}

function excludeSourceConcernFromDestination(destinationId, sourceConcernId) {
    const destination = document.getElementById(destinationId);
    if (!destination) return;
    const sourceId = parseInt(sourceConcernId || '0', 10);
    Array.from(destination.options).forEach(function(option) {
        if (!option.value) return;
        const sameConcern = parseInt(option.getAttribute('data-concern-id') || option.value, 10) === sourceId;
        option.disabled = sameConcern;
        option.hidden = sameConcern;
    });
    if (destination.value && parseInt(destination.value, 10) === sourceId) {
        destination.value = '';
    }
}

function getSelectedBatchOption() {
    const select = document.getElementById('dispatchBatchSelect');
    return select && select.value ? select.options[select.selectedIndex] : null;
}

function filterOutwardItemsByConcern(conId) {
    excludeSourceConcernFromDestination('dispatchConcernSelect', conId);
    const select = document.getElementById('dispatchBatchSelect');
    if (!select) {
        refreshFgStockAvailability();
        return;
    }
    const conIdStr = String(conId || '');
    Array.from(select.options).forEach((opt, idx) => {
        if (idx === 0) return;
        const c = opt.getAttribute('data-concern');
        if (!conIdStr || !c || String(c) === conIdStr) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });

    const currentOpt = select.options[select.selectedIndex];
    if (currentOpt && currentOpt.style.display === 'none') {
        select.value = '';
        document.getElementById('production_batch_id').value = '';
    }
    refreshFgStockAvailability();
    renderScannedTable();
}

function getSelectedFgConcernId() {
    const source = document.getElementById('sourceConcernSelect');
    return source ? parseInt(source.value || '0', 10) : 0;
}

function refreshFgStockAvailability() {
    const concernId = getSelectedFgConcernId();
    const quickSelect = document.getElementById('fgQuickSelect');
    if (quickSelect) {
        Array.from(quickSelect.options).forEach((option, index) => {
            if (index === 0) return;
            const matchesConcern = parseInt(option.dataset.concernId || '0', 10) === concernId;
            const hasStock = parseInt(option.dataset.stock || '0', 10) > 0;
            option.disabled = !matchesConcern || !hasStock;
            option.hidden = !matchesConcern || !hasStock;
        });
        quickSelect.value = '';
    }

    let removedItems = 0;
    let adjustedItems = 0;
    if (!isPosInvoiceTransfer) {
        Object.keys(scannedFgMap).forEach(id => {
            const item = scannedFgMap[id];
            const variant = availableVariants.find(v => parseInt(v.id, 10) === item.variant_id);
            if (!variant || parseInt(variant.concern_id, 10) !== concernId || parseInt(variant.stock_qty || 0, 10) <= 0) {
                delete scannedFgMap[id];
                removedItems++;
                return;
            }

            item.stock = parseInt(variant.stock_qty, 10);
            if (item.qty > item.stock) {
                item.qty = item.stock;
                adjustedItems++;
            }
        });
    }
    renderScannedTable();
    if (removedItems || adjustedItems) {
        const changes = [];
        if (removedItems) changes.push(`${removedItems} unavailable item${removedItems === 1 ? '' : 's'} removed`);
        if (adjustedItems) changes.push(`${adjustedItems} item ${adjustedItems === 1 ? 'quantity was' : 'quantities were'} reduced to available stock`);
        document.getElementById('outwardScanFeedback').innerHTML = `<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> ${changes.join('; ')} for the selected concern.</span>`;
    }
}

function processOutwardScan(scannedCode) {
    const input = document.getElementById('outwardScanInput');
    const feedback = document.getElementById('outwardScanFeedback');
    const batchSelect = document.getElementById('dispatchBatchSelect');
    if (!input || !feedback || !batchSelect) return;
    const code = String(scannedCode || input.value || '').trim();
    if (!code) {
        feedback.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Scan or enter a batch label, barcode, or SKU.</span>';
        playScannerBeep('error');
        return;
    }

    const normalizedCode = code.toUpperCase();
    const selectedConcernId = getSelectedFgConcernId();
    const batchMatches = Array.from(batchSelect.options).slice(1).filter(function(option) {
        const sameConcern = parseInt(option.getAttribute('data-concern') || '0', 10) === selectedConcernId;
        const codeMatches = [option.getAttribute('data-batch-no'), option.getAttribute('data-qc-no')]
            .some(function(batchCode) {
                return String(batchCode || '').trim().toUpperCase() === normalizedCode;
            });
        return sameConcern && codeMatches;
    });

    if (batchMatches.length > 1) {
        feedback.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i> This QC number matches multiple batches; scan the unique batch number.</span>';
        playScannerBeep('error');
        input.select();
        return;
    }
    if (batchMatches.length === 1) {
        const batch = batchMatches[0];
        const existing = getSelectedBatchOption();
        if (existing && existing.value !== batch.value) {
            feedback.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i> One production batch is allowed per outward manifest. Clear the selected batch before scanning another.</span>';
            playScannerBeep('error');
            input.select();
            return;
        }
        batchSelect.value = batch.value;
        document.getElementById('production_batch_id').value = batch.value;
        feedback.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i> Production batch ${batch.getAttribute('data-batch-no')} added (${batch.getAttribute('data-available-qty')} good pcs available).</span>`;
        playScannerBeep('success');
        input.value = '';
        renderScannedTable();
        input.focus();
        return;
    }

    processFinishedGoodScan(code);
}

function openNewDispatchModal() {
    new bootstrap.Modal(document.getElementById('modalAddOutward')).show();
    if (!isPosInvoiceTransfer) {
        setTimeout(() => {
            const input = document.getElementById('outwardScanInput');
            if (input) input.focus();
        }, 150);
    }
}

// Web Audio API beep sound for realistic barcode scanner feedback
function playScannerBeep(type = 'success') {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(1400, audioCtx.currentTime); // crisp high beep
            gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.09);
        } else {
            osc.frequency.setValueAtTime(350, audioCtx.currentTime); // low buzz
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.2);
        }
    } catch (e) {
        // AudioContext not allowed or unsupported
    }
}

function processFinishedGoodScan(code) {
    const input = document.getElementById('outwardScanInput');
    const feedback = document.getElementById('outwardScanFeedback');
    // Match code against variants: by SKU, ID, or partial name
    const selectedConcernId = getSelectedFgConcernId();
    const match = availableVariants.find(v =>
        parseInt(v.concern_id, 10) === selectedConcernId &&
        parseInt(v.stock_qty || 0, 10) > 0 &&
        (
            (v.sku_code && v.sku_code.toLowerCase() === code.toLowerCase()) ||
            (v.name && v.name.toLowerCase().includes(code.toLowerCase())) ||
            code.toLowerCase().includes(String(v.id))
        )
    );

    if (match) {
        if (!addScannedVariant(match)) return;
        feedback.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Added finished good: ${match.name} (${match.sku_code}), +1 piece.</span>`;
        playScannerBeep('success');
        input.value = '';
        input.focus();
    } else {
        feedback.innerHTML = `<span class="text-danger fw-semibold"><i class="bi bi-x-circle me-1"></i> No available QC-passed batch or finished good matched '${code}'.</span>`;
        playScannerBeep('error');
        input.select();
    }
}

function quickAddFromSelect(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    const vId = parseInt(opt.value);
    const match = availableVariants.find(v => parseInt(v.id) === vId);
    if (match) {
        if (!addScannedVariant(match)) {
            select.value = '';
            return;
        }
        document.getElementById('outwardScanFeedback').innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i> Added: ${match.name} (${match.sku_code}).</span>`;
        playScannerBeep('success');
    }
    select.value = '';
}

function addScannedVariant(v) {
    const id = parseInt(v.id);
    const stock = parseInt(v.stock_qty || 0, 10);
    if (parseInt(v.concern_id, 10) !== getSelectedFgConcernId() || stock <= 0) {
        document.getElementById('outwardScanFeedback').innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i> This item has no available good stock in the selected concern Main Store.</span>';
        playScannerBeep('error');
        return false;
    }
    if (scannedFgMap[id] && scannedFgMap[id].qty >= stock) {
        document.getElementById('outwardScanFeedback').innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i> Cannot add more; only ${stock} pcs are available.</span>`;
        playScannerBeep('error');
        return false;
    }
    if (!scannedFgMap[id]) {
        scannedFgMap[id] = {
            variant_id: id,
            concern_id: parseInt(v.concern_id, 10),
            name: v.name,
            sku: v.sku_code || 'SKU-' + id,
            size: v.size || 'Free',
            stock: parseInt(v.stock_qty || 0),
            qty: 1
        };
    } else {
        scannedFgMap[id].qty += 1;
    }
    renderScannedTable();
    return true;
}

function updateScannedQty(id, delta) {
    if (!scannedFgMap[id]) return;
    const item = scannedFgMap[id];
    if (delta > 0 && (item.stock <= 0 || item.qty + delta > item.stock)) {
        document.getElementById('outwardScanFeedback').innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i> Quantity cannot exceed ${item.stock} available pcs.</span>`;
        playScannerBeep('error');
        return;
    }
    item.qty += delta;
    if (scannedFgMap[id].qty <= 0) {
        delete scannedFgMap[id];
    }
    renderScannedTable();
}

function setScannedQtyManual(id, input) {
    const val = parseInt(input.value) || 0;
    if (val <= 0) {
        delete scannedFgMap[id];
    } else if (val > scannedFgMap[id].stock) {
        scannedFgMap[id].qty = Math.max(1, scannedFgMap[id].stock);
        document.getElementById('outwardScanFeedback').innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i> Quantity was limited to ${scannedFgMap[id].stock} available pcs.</span>`;
        playScannerBeep('error');
    } else {
        scannedFgMap[id].qty = val;
    }
    renderScannedTable();
}

function removeScannedItem(id) {
    delete scannedFgMap[id];
    renderScannedTable();
}

function clearSelectedBatch() {
    const batchSelect = document.getElementById('dispatchBatchSelect');
    if (batchSelect) batchSelect.value = '';
    document.getElementById('production_batch_id').value = '';
    renderScannedTable();
}

function clearAllScanned() {
    scannedFgMap = {};
    clearSelectedBatch();
    renderScannedTable();
    document.getElementById('outwardScanFeedback').innerHTML = '<span class="text-muted"><i class="bi bi-info-circle me-1"></i> Scanned items cleared.</span>';
}

function renderScannedTable() {
    const tbody = document.getElementById('fgScannedTbody');
    const items = Object.values(scannedFgMap);
    const batch = getSelectedBatchOption();
    const countBadge = document.getElementById('fgItemsCount');
    const pcsBadge = document.getElementById('fgTotalPcsBadge');
    const jsonInput = document.getElementById('scanned_items_json');

    countBadge.innerText = items.length + (batch ? 1 : 0);

    let totalPcs = batch ? parseFloat(batch.getAttribute('data-available-qty') || '0') : 0;
    items.forEach(it => { totalPcs += it.qty; });
    pcsBadge.innerText = totalPcs + ' pcs';

    jsonInput.value = JSON.stringify(items);

    if (items.length === 0 && !batch) {
        tbody.innerHTML = `
            <tr id="emptyFgRow">
                <td colspan="5" class="text-center py-4 text-muted small">
                    <i class="bi bi-upc-scan fs-3 d-block text-secondary opacity-50 mb-1"></i>
                    Scan a production batch or finished-good product to add it here.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    if (batch) {
        const batchNo = batch.getAttribute('data-batch-no') || batch.textContent.trim();
        const qcNo = batch.getAttribute('data-qc-no') || '-';
        const batchQty = parseFloat(batch.getAttribute('data-available-qty') || '0');
        html += `
            <tr>
                <td class="ps-3 py-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Production batch</span>
                </td>
                <td class="font-monospace fw-semibold">${batchNo}<small class="d-block text-muted fw-normal">QC ${qcNo}</small></td>
                <td><span class="badge bg-light text-dark border">Multiple</span></td>
                <td><span class="badge bg-success-subtle text-success border border-success-subtle">Available: ${batchQty} pcs</span></td>
                <td><span class="fw-bold font-monospace">${batchQty}</span></td>
                <td class="text-end pe-3">
                    <button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="clearSelectedBatch()" title="Remove batch"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `;
    }
    items.forEach((it, idx) => {
        const isOverStock = it.stock <= 0 || it.qty > it.stock;
        const stockLabel = isPosInvoiceTransfer
            ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">POS invoice</span>'
            : `<span class="badge ${isOverStock ? 'bg-danger' : 'bg-success-subtle text-success border border-success-subtle'}">Available: ${it.stock} pcs</span>`;
        const quantityControl = isPosInvoiceTransfer
            ? `<span class="fw-bold font-monospace">${it.qty}</span>`
            : `<div class="input-group input-group-sm" style="width: 120px;">
                    <button class="btn btn-outline-secondary px-2" type="button" onclick="updateScannedQty(${it.variant_id}, -1)">-</button>
                    <input type="number" class="form-control text-center px-1 font-monospace fw-bold" value="${it.qty}" min="1" max="${it.stock}" onchange="setScannedQtyManual(${it.variant_id}, this)">
                    <button class="btn btn-outline-secondary px-2" type="button" onclick="updateScannedQty(${it.variant_id}, 1)" ${it.stock <= 0 || it.qty >= it.stock ? 'disabled' : ''}>+</button>
                </div>`;
        const removeAction = isPosInvoiceTransfer
            ? '<span class="text-muted">-</span>'
            : `<button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="removeScannedItem(${it.variant_id})" title="Remove"><i class="bi bi-trash"></i></button>`;
        html += `
            <tr>
                <td class="ps-3 py-2">
                    <div class="fw-semibold text-dark">${it.name}</div>
                </td>
                <td><span class="font-monospace fw-semibold text-primary">${it.sku}</span></td>
                <td><span class="badge bg-light text-dark border">${it.size}</span></td>
                <td>${stockLabel}</td>
                <td>${quantityControl}</td>
                <td class="text-end pe-3">${removeAction}</td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
    const scanInput = document.getElementById('outwardScanInput');
    const scanButton = document.getElementById('outwardScanButton');
    if (scanButton) scanButton.addEventListener('click', function() { processOutwardScan(); });
    if (scanInput) {
        scanInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processOutwardScan();
            }
        });
    }

    const sourceConcernSelect = document.getElementById('sourceConcernSelect');
    if (sourceConcernSelect) {
        sourceConcernSelect.addEventListener('change', function() {
            filterOutwardItemsByConcern(sourceConcernSelect.value);
        });
        filterOutwardItemsByConcern(sourceConcernSelect.value);
    }

    const form = document.getElementById('formAddOutward');
    if (form) {
        renderScannedTable();
        form.addEventListener('submit', function(e) {
            const batch = getSelectedBatchOption();
            const items = Object.values(scannedFgMap);
            if (!batch && items.length === 0) {
                e.preventDefault();
                alert('Scan a production batch or add at least one finished-good product before dispatching.');
                return false;
            }
            if (isPosInvoiceTransfer && batch) {
                e.preventDefault();
                alert('POS invoice handoff cannot include a production batch.');
                return false;
            }
            if (!isPosInvoiceTransfer && items.some(item =>
                item.concern_id !== getSelectedFgConcernId() ||
                item.stock <= 0 ||
                item.qty > item.stock
            )) {
                e.preventDefault();
                alert('One or more finished-good quantities exceed the available good Main Store stock.');
                return false;
            }
        });
    }

    if (isPosInvoiceTransfer) {
        openNewDispatchModal();
    }
});

function openEditOutwardModal(o) {
    if (!o) return;
    document.getElementById('edit_outward_id').value = o.id || '';
    document.getElementById('edit_outward_no').value = o.outward_no || '';
    document.getElementById('edit_vehicle_number').value = o.display_vehicle || o.vehicle_number || '';
    document.getElementById('edit_total_boxes').value = o.cartons_count || o.total_boxes || 1;
    document.getElementById('edit_driver_name').value = o.driver_name || '';
    document.getElementById('edit_driver_contact').value = o.driver_contact || '';
    document.getElementById('edit_warehouse_name').value = o.display_warehouse_name || o.warehouse_name || '';
    if (document.getElementById('edit_transporter_name')) {
        document.getElementById('edit_transporter_name').value = o.transporter_name || '';
    }
    if (document.getElementById('edit_notes')) {
        document.getElementById('edit_notes').value = o.notes || '';
    }
    const editCon = document.getElementById('edit_destination_concern_id');
    if (editCon) {
        excludeSourceConcernFromDestination('edit_destination_concern_id', o.concern_id);
        const destinationId = Number(o.destination_concern_id || 0);
        editCon.value = destinationId === Number(o.concern_id) ? '' : String(destinationId || '');
    }
    const statusSelect = document.getElementById('edit_status');
    const rawStatus = (o.status || 'DISPATCHED').trim().toUpperCase();
    if (statusSelect) {
        let matched = false;
        Array.from(statusSelect.options).forEach(function(opt) {
            if (opt.value.toUpperCase() === rawStatus) {
                statusSelect.value = opt.value;
                matched = true;
            }
        });
        if (!matched) {
            const newOpt = document.createElement('option');
            newOpt.value = rawStatus;
            newOpt.textContent = '✔ ' + rawStatus;
            statusSelect.appendChild(newOpt);
            statusSelect.value = rawStatus;
        }
    }

    new bootstrap.Modal(document.getElementById('modalEditOutward')).show();
}

function openTraceabilityModal(outwardNo, id, concernId) {
    document.getElementById('traceBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="small text-muted mt-2">Loading traceability timeline for ${outwardNo}...</div>
        </div>
    `;
    new bootstrap.Modal(document.getElementById('modalTraceability')).show();

    const concernScope = concernId ? '?concern_id=' + encodeURIComponent(concernId) : '';
    fetch('<?= \App\Core\Controller::url('/m/outward/traceability/') ?>' + id + concernScope)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                document.getElementById('traceBody').innerHTML = '<div class="alert alert-danger">' + (data.error || 'Failed to load') + '</div>';
                return;
            }

            let itemsHtml = '';
            if (data.items && data.items.length > 0) {
                itemsHtml = `
                    <div class="col-12 mt-2">
                        <div class="card border rounded-3 p-3 bg-white">
                            <small class="fw-bold text-dark text-uppercase d-block mb-2">Itemized Finishing Goods Breakdown</small>
                            <table class="table table-sm table-bordered mb-0 small">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Product Name</th>
                                        <th>SKU Code</th>
                                        <th>Size</th>
                                        <th class="text-end">Dispatched Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.items.map(it => `
                                        <tr>
                                            <td>${it.variant_name}</td>
                                            <td class="font-monospace">${it.sku_code}</td>
                                            <td>${it.size}</td>
                                            <td class="text-end fw-bold text-success">${parseInt(it.qty)} pcs</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }

            var html = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <small class="text-muted d-block mb-1">Outward Gate Pass</small>
                            <div class="fw-bold font-monospace fs-5 text-primary">${data.outward_no}</div>
                            <small class="text-muted d-block mt-2 mb-1">Dispatch Type / Batch</small>
                            <div class="fw-bold text-dark font-monospace">${data.batch_no}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <small class="text-muted d-block mb-1">Product & Total Quantity</small>
                            <div class="fw-bold text-dark">${data.product_name} (${data.size})</div>
                            <div class="fs-5 fw-bold text-success mt-1">${data.pieces_count} pcs <span class="fs-6 text-muted font-normal">(${data.cartons_count} box)</span></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="row g-2 small">
                                <div class="col-md-4">
                                    <span class="text-muted">Origin Facility / Unit:</span><br>
                                    <strong>${data.production_unit} (${data.source_concern})</strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Destination Target:</span><br>
                                    <strong>${data.destination}</strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Carrier / Vehicle:</span><br>
                                    <span class="badge bg-dark font-monospace">${data.vehicle_number}</span>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <span class="text-muted">Driver Details:</span><br>
                                    <strong>${data.driver_name} (${data.driver_contact})</strong>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <span class="text-muted">Dispatched At:</span><br>
                                    <strong>${data.dispatched_at}</strong>
                                </div>
                                <div class="col-md-4 mt-2">
                                    <span class="text-muted">Status:</span><br>
                                    <span class="badge bg-success">${data.status}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    ${itemsHtml}
                </div>
            `;
            document.getElementById('traceBody').innerHTML = html;
        })
        .catch(function(err) {
            document.getElementById('traceBody').innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
        });
}

</script>
