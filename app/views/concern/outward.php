<?php
// app/views/concern/outward.php
// Production Outward & Dispatch View (With Production Batch & Finishing Good Scanner Flow)
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
            <p class="text-secondary small mb-0">Dispatch finished production boxes and scanned finishing goods with vehicle tracking</p>
        </div>
        <?php if (\App\Core\Auth::can('outward', 'create')): ?>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary fw-semibold shadow-sm px-3 py-2" onclick="openNewDispatchModal('batch')">
                <i class="bi bi-boxes me-1"></i> New Batch Outward
            </button>
            <button type="button" class="btn btn-primary fw-semibold shadow-sm px-3 py-2" onclick="openNewDispatchModal('finished_goods')" style="background:#4f46e5; border-color:#4f46e5;">
                <i class="bi bi-upc-scan me-1"></i> Scan Finishing Goods
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <!-- Top Navigation: Two Primary Labels (1. Production Batches | 2. Finishing Good) + Filter & Search -->
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
            <!-- Two Flow Labels / Tabs -->
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold text-muted text-uppercase d-none d-md-inline" style="font-size:0.75rem; letter-spacing:0.05em;">DISPATCH TYPE:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="<?= \App\Core\Controller::url('/production/outward?tab=batch' . ($selectedConcernId ? '&concern_id=' . $selectedConcernId : '') . (!empty($search) ? '&search=' . urlencode($search) : '')) ?>" class="btn <?= ($activeTab === 'batch') ? 'btn-primary' : 'btn-outline-secondary' ?> fw-semibold px-3 py-1.5 d-flex align-items-center gap-1.5" style="<?= ($activeTab === 'batch') ? 'background:#4f46e5; border-color:#4f46e5;' : '' ?>">
                        <i class="bi bi-boxes"></i> 1. Production Batches
                        <span class="badge <?= ($activeTab === 'batch') ? 'bg-white text-dark' : 'bg-secondary text-white' ?> ms-1"><?= (int)$batchCount ?></span>
                    </a>
                    <a href="<?= \App\Core\Controller::url('/production/outward?tab=fg' . ($selectedConcernId ? '&concern_id=' . $selectedConcernId : '') . (!empty($search) ? '&search=' . urlencode($search) : '')) ?>" class="btn <?= ($activeTab === 'fg') ? 'btn-primary' : 'btn-outline-secondary' ?> fw-semibold px-3 py-1.5 d-flex align-items-center gap-1.5" style="<?= ($activeTab === 'fg') ? 'background:#4f46e5; border-color:#4f46e5;' : '' ?>">
                        <i class="bi bi-upc-scan"></i> 2. Finishing Good
                        <span class="badge <?= ($activeTab === 'fg') ? 'bg-white text-dark' : 'bg-secondary text-white' ?> ms-1"><?= (int)$fgCount ?></span>
                    </a>
                </div>
                <span class="badge bg-light text-dark border px-2 py-1 ms-2" style="font-size:0.75rem;">
                    <?= (int)$totalRecords ?> Total
                </span>
            </div>

            <!-- Toolbar: Dynamic Concern Filter and Search -->
            <form method="GET" action="<?= \App\Core\Controller::url('/production/outward') ?>" class="d-flex flex-wrap align-items-center gap-2 m-0" style="max-width: 500px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

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
                        <a href="<?= \App\Core\Controller::url('/production/outward?tab=' . urlencode($activeTab) . ($selectedConcernId ? '&concern_id=' . $selectedConcernId : '')) ?>" class="btn btn-outline-secondary" title="Clear">
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
                                <?php if ($activeTab === 'fg'): ?>
                                    No finishing good dispatches found. Click <strong>Scan Finishing Goods</strong> to scan products and outward.
                                <?php else: ?>
                                    No production batch outward records found. Click <strong>New Batch Outward</strong> to dispatch a batch.
                                <?php endif; ?>
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
                                        <button type="button" class="btn btn-sm btn-outline-info p-1 px-2" title="Traceability Details" onclick="openTraceabilityModal('<?= htmlspecialchars($o['outward_no']) ?>', <?= (int)$o['id'] ?>)">
                                            <i class="bi bi-link-45deg fs-6"></i>
                                        </button>

                                        <!-- 2. Edit Dispatch Modal -->
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
                        <a class="page-link" href="<?= \App\Core\Controller::url('/production/outward?page=' . max(1, $page - 1) . '&tab=' . urlencode($activeTab) . '&search=' . urlencode($search ?? '') . '&concern_id=' . urlencode((string)($selectedConcernId ?? ''))) ?>">&lt; Prev</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($page === $p) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= \App\Core\Controller::url('/production/outward?page=' . $p . '&tab=' . urlencode($activeTab) . '&search=' . urlencode($search ?? '') . '&concern_id=' . urlencode((string)($selectedConcernId ?? ''))) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= \App\Core\Controller::url('/production/outward?page=' . min($totalPages, $page + 1) . '&tab=' . urlencode($activeTab) . '&search=' . urlencode($search ?? '') . '&concern_id=' . urlencode((string)($selectedConcernId ?? ''))) ?>">Next &gt;</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal: New Outward Dispatch (Two Labels: 1. Production Batch | 2. Finishing Good) -->
<div class="modal fade" id="modalAddOutward" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= \App\Core\Controller::url('/m/outward/store') ?>" method="POST" id="formAddOutward" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="scanned_items_json" id="scanned_items_json" value="[]">

            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i> New Outward Dispatch Manifest</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Two Primary Flow Labels Switcher -->
                <div class="mb-4">
                    <label class="form-label small fw-bold text-dark mb-2 text-uppercase" style="letter-spacing:0.05em;">Choose Outward Dispatch Flow *</label>
                    <div class="btn-group w-100 p-1 bg-light rounded-3 shadow-none border" role="group">
                        <input type="radio" class="btn-check" name="dispatch_mode" id="mode_batch" value="batch" checked onchange="toggleDispatchMode('batch')">
                        <label class="btn btn-outline-primary fw-bold py-2.5 rounded-2 d-flex align-items-center justify-content-center gap-2" for="mode_batch">
                            <i class="bi bi-boxes fs-5"></i>
                            <span>1. Production Batch</span>
                        </label>

                        <input type="radio" class="btn-check" name="dispatch_mode" id="mode_fg" value="finished_goods" onchange="toggleDispatchMode('finished_goods')">
                        <label class="btn btn-outline-primary fw-bold py-2.5 rounded-2 d-flex align-items-center justify-content-center gap-2" for="mode_fg">
                            <i class="bi bi-upc-scan fs-5"></i>
                            <span>2. Finishing Good (Scan &amp; Outward)</span>
                        </label>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- FLOW 1: PRODUCTION BATCH DISPATCH (Show the batches)                      -->
                <!-- ========================================================================= -->
                <div id="sectionBatchMode" class="row g-3">
                    <div class="col-12">
                        <div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle fs-5"></i>
                            <div>Select an approved pre-packaged production batch (BAT-XXXX) and choose the destination concern / warehouse.</div>
                        </div>
                    </div>

                    <!-- Row 1: Dual Concern Routing: Current Concern -> Destination Concern -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i> Current Concern (Dispatched From) *
                        </label>
                        <select name="source_concern_id" id="sourceConcernSelect" class="form-select form-select-sm bg-light fw-semibold" onchange="filterBatchesByConcern(this.value)">
                            <?php foreach ($allConcerns as $con): ?>
                                <option value="<?= $con['id'] ?>" <?= ((int)$currentConcernId === (int)$con['id']) ? 'selected' : '' ?>>
                                    🏢 <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                            <i class="bi bi-info-circle me-1"></i> Current concern we are currently in (origin facility)
                        </small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark">
                            <i class="bi bi-truck text-primary me-1"></i> Destination Concern (Outward To Concern) *
                        </label>
                        <select name="destination_concern_id" id="dispatchConcernSelect" class="form-select form-select-sm fw-semibold border-primary" required>
                            <option value="">-- Select Outward Destination Concern --</option>
                            <?php foreach ($allConcerns as $con): ?>
                                <option value="<?= $con['id'] ?>">
                                    🏢 <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                            <i class="bi bi-arrow-right-circle me-1"></i> Select from all created concerns which we outward to
                        </small>
                    </div>

                    <!-- Row 2: Batch Selection & Warehouse -->
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold text-secondary">
                            <i class="bi bi-box-seam text-primary me-1"></i> Select Production Batch to Dispatch *
                        </label>
                        <select name="batch_id" id="dispatchBatchSelect" class="form-select form-select-sm" required>
                            <option value="">-- Select Finished Production Batch --</option>
                            <?php foreach ($availableBatches as $b): ?>
                                <option value="<?= $b['id'] ?>" data-concern="<?= (int)($b['concern_id'] ?? 1) ?>">
                                    <?= htmlspecialchars($b['batch_no']) ?> &mdash; <?= htmlspecialchars($b['variant_name']) ?> (<?= (int)$b['batch_qty'] ?> pcs, <?= (int)$b['total_boxes'] ?> box)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small fw-semibold text-secondary">
                            <i class="bi bi-archive me-1"></i> Destination Warehouse / Facility *
                        </label>
                        <select name="warehouse_name" class="form-select form-select-sm">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= htmlspecialchars($wh['name']) ?>" <?= str_contains($wh['name'], 'wareouse') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- FLOW 2: FINISHING GOOD (Scan finished good products and outward)          -->
                <!-- ========================================================================= -->
                <div id="sectionFgMode" class="d-none">
                    <!-- Scanner Input Bar -->
                    <div class="card border-0 bg-light p-3 rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-upc-scan text-primary fs-5"></i> Scan Finishing Good Product QR / Barcode / SKU
                            </label>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small">
                                Live Scanner Ready
                            </span>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-qr-code-scan"></i></span>
                            <input type="text" id="fgScanInput" class="form-control font-monospace fs-6" placeholder="Scan Barcode or type SKU (e.g. GRM-STY-0001-XL, KBD1126101)..." autocomplete="off">
                            <button type="button" class="btn btn-primary px-3 fw-bold" onclick="processFgScanInput()">
                                <i class="bi bi-plus-lg me-1"></i> Scan / Add
                            </button>
                        </div>
                        <div id="fgScanFeedback" class="small mt-1 text-muted" style="min-height: 20px;">
                            <i class="bi bi-info-circle me-1"></i> Point your handheld barcode scanner or type code and press Enter.
                        </div>

                        <!-- Manual Quick Select Dropdown Fallback -->
                        <div class="mt-2 pt-2 border-top">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-8">
                                    <select id="fgQuickSelect" class="form-select form-select-sm" onchange="quickAddFromSelect(this)">
                                        <option value="">-- Or Quick Select Finishing Good Product --</option>
                                        <?php foreach ($stockVariants as $sv): ?>
                                            <option value="<?= $sv['id'] ?>" data-sku="<?= htmlspecialchars($sv['sku_code']) ?>" data-name="<?= htmlspecialchars($sv['name']) ?>" data-size="<?= htmlspecialchars($sv['size'] ?? 'Free') ?>" data-stock="<?= (int)$sv['stock_qty'] ?>">
                                                <?= htmlspecialchars($sv['name']) ?> (<?= htmlspecialchars($sv['sku_code']) ?> - <?= htmlspecialchars($sv['size']) ?>) &mdash; Stock: <?= (int)$sv['stock_qty'] ?> pcs
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
                            <span class="small fw-bold text-dark"><i class="bi bi-list-check me-1 text-primary"></i> Scanned Finishing Goods Items (<span id="fgItemsCount">0</span>)</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" id="fgTotalPcsBadge">0 pcs scanned</span>
                        </div>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0" id="tableFgScanned">
                                <thead class="bg-light text-muted small text-uppercase" style="font-size:0.7rem;">
                                    <tr>
                                        <th class="ps-3 py-2">PRODUCT &amp; SKU</th>
                                        <th>SIZE</th>
                                        <th>AVAILABLE STOCK</th>
                                        <th style="width: 140px;">SCANNED PIECES</th>
                                        <th class="text-end pe-3" style="width: 60px;">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody id="fgScannedTbody">
                                    <tr id="emptyFgRow">
                                        <td colspan="5" class="text-center py-4 text-muted small">
                                            <i class="bi bi-upc-scan fs-3 d-block text-secondary opacity-50 mb-1"></i>
                                            No finishing good products scanned yet. Scan barcodes or select products above.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Destination Concern (All created concerns listed) & Boxes for Finishing Goods -->
                    <div class="row g-3 mb-2 p-3 bg-light rounded-3 border">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> Current Concern (Dispatched From) *
                            </label>
                            <select name="source_concern_id_fg" id="fgSourceConcernSelect" class="form-select form-select-sm bg-white fw-semibold">
                                <?php foreach ($allConcerns as $con): ?>
                                    <option value="<?= $con['id'] ?>" <?= ((int)$currentConcernId === (int)$con['id']) ? 'selected' : '' ?>>
                                        🏢 <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                                <i class="bi bi-info-circle me-1"></i> Current concern we are currently in (origin facility)
                            </small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">
                                <i class="bi bi-truck text-primary me-1"></i> Destination Concern (Outward To Concern) *
                            </label>
                            <select name="destination_concern_id_fg" id="fgDestConcernSelect" class="form-select form-select-sm fw-semibold border-primary">
                                <option value="">-- Select Outward Destination Concern --</option>
                                <?php foreach ($allConcerns as $con): ?>
                                    <option value="<?= $con['id'] ?>">
                                        🏢 <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                                <i class="bi bi-arrow-right-circle me-1"></i> Select from all created concerns which we outward to
                            </small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">
                                <i class="bi bi-archive me-1"></i> Destination Warehouse
                            </label>
                            <select name="destination_warehouse_fg" id="fgDestWarehouse" class="form-select form-select-sm">
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= htmlspecialchars($wh['name']) ?>" <?= str_contains($wh['name'], 'wareouse') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wh['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">
                                <i class="bi bi-boxes me-1"></i> Total Boxes / Cartons
                            </label>
                            <input type="number" name="total_boxes" id="fgTotalBoxesInput" class="form-control form-control-sm" value="1" min="1" step="1">
                        </div>
                    </div>
                </div>

                <hr class="my-3 text-muted">

                <!-- SECTION C: Common Logistics & Vehicle Info (Matches Reference Screenshot) -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Vehicle Number *</label>
                        <input type="text" name="vehicle_number" class="form-control form-control-sm font-monospace text-uppercase" placeholder="e.g. rtt31232" value="rtt31232" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Driver Name</label>
                        <input type="text" name="driver_name" class="form-control form-control-sm" placeholder="e.g. Vanaja" value="Vanaja">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Driver Contact</label>
                        <input type="text" name="driver_contact" class="form-control form-control-sm" placeholder="e.g. 9943557998" value="9943557998">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Transporter / Carrier Name</label>
                        <input type="text" name="transporter_name" class="form-control form-control-sm" placeholder="e.g. Express Logistics" value="GRM In-house Transport">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Notes / Remarks</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Priority shipment">
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

<!-- Modal 2: Edit Outward Dispatch -->
<div class="modal fade" id="modalEditOutward" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/outward/update') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="id" id="edit_outward_id">
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
                                <option value="<?= $con['id'] ?>">
                                    🏢 <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Destination Warehouse / Facility</label>
                        <input type="text" name="warehouse_name" id="edit_warehouse_name" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-semibold text-secondary">Status</label>
                        <select name="status" id="edit_status" class="form-select form-select-sm">
                            <option value="DISPATCHED">✔ DISPATCHED</option>
                            <option value="IN_TRANSIT">🚚 IN TRANSIT</option>
                            <option value="DELIVERED">📦 DELIVERED</option>
                            <option value="PREPARING">⏳ PREPARING</option>
                        </select>
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

// Scanned items dictionary: { variant_id: { variant_id, name, sku, size, stock, qty } }
let scannedFgMap = {};

function filterBatchesByConcern(conId) {
    const select = document.getElementById('dispatchBatchSelect');
    if (!select) return;
    const conIdStr = String(conId || '');
    let matched = 0;
    Array.from(select.options).forEach((opt, idx) => {
        if (idx === 0) return;
        const c = opt.getAttribute('data-concern');
        if (!conIdStr || !c || String(c) === conIdStr) {
            opt.style.display = '';
            matched++;
        } else {
            opt.style.display = 'none';
        }
    });

    const currentOpt = select.options[select.selectedIndex];
    if (currentOpt && currentOpt.style.display === 'none') {
        select.value = '';
    }
}

function toggleDispatchMode(mode) {
    const batchSec = document.getElementById('sectionBatchMode');
    const fgSec = document.getElementById('sectionFgMode');
    const batchSelect = document.getElementById('dispatchBatchSelect');
    const dispatchConcernSelect = document.getElementById('dispatchConcernSelect');
    const fgDestConcernSelect = document.getElementById('fgDestConcernSelect');
    const btnSubmit = document.getElementById('btnSubmitOutward');

    if (mode === 'batch') {
        batchSec.classList.remove('d-none');
        fgSec.classList.add('d-none');
        if (batchSelect) batchSelect.required = true;
        if (dispatchConcernSelect) dispatchConcernSelect.required = true;
        if (fgDestConcernSelect) fgDestConcernSelect.required = false;
        btnSubmit.innerHTML = '<i class="bi bi-box-arrow-up-right me-1"></i> Confirm & Dispatch Production Batch';
    } else {
        batchSec.classList.add('d-none');
        fgSec.classList.remove('d-none');
        if (batchSelect) batchSelect.required = false;
        if (dispatchConcernSelect) dispatchConcernSelect.required = false;
        if (fgDestConcernSelect) fgDestConcernSelect.required = true;
        btnSubmit.innerHTML = '<i class="bi bi-box-arrow-up-right me-1"></i> Confirm & Outward Finishing Goods';
        setTimeout(() => {
            const input = document.getElementById('fgScanInput');
            if (input) input.focus();
        }, 150);
    }
}

function openNewDispatchModal(mode) {
    const radioBatch = document.getElementById('mode_batch');
    const radioFg = document.getElementById('mode_fg');

    if (mode === 'batch') {
        if (radioBatch) radioBatch.checked = true;
        toggleDispatchMode('batch');
    } else {
        if (radioFg) radioFg.checked = true;
        toggleDispatchMode('finished_goods');
    }

    new bootstrap.Modal(document.getElementById('modalAddOutward')).show();
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

// Process scanner input
function processFgScanInput() {
    const input = document.getElementById('fgScanInput');
    const feedback = document.getElementById('fgScanFeedback');
    const code = (input.value || '').trim();

    if (!code) {
        feedback.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Please scan or enter a barcode / SKU code.</span>';
        playScannerBeep('error');
        return;
    }

    // Match code against variants: by SKU, ID, or partial name
    const match = availableVariants.find(v => 
        (v.sku_code && v.sku_code.toLowerCase() === code.toLowerCase()) ||
        (v.name && v.name.toLowerCase().includes(code.toLowerCase())) ||
        (code.toLowerCase().includes(String(v.id)))
    );

    if (match) {
        addScannedVariant(match);
        feedback.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Scanned: ${match.name} (${match.sku_code})! +1 piece added.</span>`;
        playScannerBeep('success');
        input.value = '';
        input.focus();
    } else {
        feedback.innerHTML = `<span class="text-danger fw-semibold"><i class="bi bi-x-circle me-1"></i> No matching finishing good found for '${code}'.</span>`;
        playScannerBeep('error');
    }
}

function quickAddFromSelect(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    const vId = parseInt(opt.value);
    const match = availableVariants.find(v => parseInt(v.id) === vId);
    if (match) {
        addScannedVariant(match);
        document.getElementById('fgScanFeedback').innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i> Added: ${match.name} (${match.sku_code}).</span>`;
        playScannerBeep('success');
    }
    select.value = '';
}

function addScannedVariant(v) {
    const id = parseInt(v.id);
    if (!scannedFgMap[id]) {
        scannedFgMap[id] = {
            variant_id: id,
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
}

function updateScannedQty(id, delta) {
    if (!scannedFgMap[id]) return;
    scannedFgMap[id].qty += delta;
    if (scannedFgMap[id].qty <= 0) {
        delete scannedFgMap[id];
    }
    renderScannedTable();
}

function setScannedQtyManual(id, input) {
    const val = parseInt(input.value) || 0;
    if (val <= 0) {
        delete scannedFgMap[id];
    } else {
        scannedFgMap[id].qty = val;
    }
    renderScannedTable();
}

function removeScannedItem(id) {
    delete scannedFgMap[id];
    renderScannedTable();
}

function clearAllScanned() {
    scannedFgMap = {};
    renderScannedTable();
    document.getElementById('fgScanFeedback').innerHTML = '<span class="text-muted"><i class="bi bi-info-circle me-1"></i> Scanned items cleared.</span>';
}

function renderScannedTable() {
    const tbody = document.getElementById('fgScannedTbody');
    const items = Object.values(scannedFgMap);
    const countBadge = document.getElementById('fgItemsCount');
    const pcsBadge = document.getElementById('fgTotalPcsBadge');
    const jsonInput = document.getElementById('scanned_items_json');

    countBadge.innerText = items.length;

    let totalPcs = 0;
    items.forEach(it => { totalPcs += it.qty; });
    pcsBadge.innerText = totalPcs + ' pcs scanned';

    jsonInput.value = JSON.stringify(items);

    if (items.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyFgRow">
                <td colspan="5" class="text-center py-4 text-muted small">
                    <i class="bi bi-upc-scan fs-3 d-block text-secondary opacity-50 mb-1"></i>
                    No finishing good products scanned yet. Scan barcodes or select products above.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    items.forEach((it, idx) => {
        const isOverStock = it.stock > 0 && it.qty > it.stock;
        html += `
            <tr>
                <td class="ps-3 py-2">
                    <div class="fw-semibold text-dark">${it.name}</div>
                    <small class="text-muted font-monospace">${it.sku}</small>
                </td>
                <td><span class="badge bg-light text-dark border">${it.size}</span></td>
                <td>
                    <span class="badge ${isOverStock ? 'bg-danger' : 'bg-success-subtle text-success border border-success-subtle'}">
                        Stock: ${it.stock} pcs
                    </span>
                </td>
                <td>
                    <div class="input-group input-group-sm" style="width: 120px;">
                        <button class="btn btn-outline-secondary px-2" type="button" onclick="updateScannedQty(${it.variant_id}, -1)">-</button>
                        <input type="number" class="form-control text-center px-1 font-monospace fw-bold" value="${it.qty}" min="1" onchange="setScannedQtyManual(${it.variant_id}, this)">
                        <button class="btn btn-outline-secondary px-2" type="button" onclick="updateScannedQty(${it.variant_id}, 1)">+</button>
                    </div>
                </td>
                <td class="text-end pe-3">
                    <button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="removeScannedItem(${it.variant_id})" title="Remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

// Scanner enter key handler
document.addEventListener('DOMContentLoaded', function() {
    const scanInput = document.getElementById('fgScanInput');
    if (scanInput) {
        scanInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processFgScanInput();
            }
        });
    }

    // Form submit validation for Finishing Good mode
    const form = document.getElementById('formAddOutward');
    if (form) {
        form.addEventListener('submit', function(e) {
            const isFg = document.getElementById('mode_fg').checked;
            if (isFg) {
                const items = Object.values(scannedFgMap);
                if (items.length === 0) {
                    e.preventDefault();
                    alert('Please scan or add at least 1 finishing good product before dispatching!');
                    return false;
                }
            }
        });
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
    const editCon = document.getElementById('edit_destination_concern_id');
    if (editCon) editCon.value = o.destination_concern_id || '';
    document.getElementById('edit_status').value = (o.status || 'DISPATCHED').toUpperCase();

    new bootstrap.Modal(document.getElementById('modalEditOutward')).show();
}

function openTraceabilityModal(outwardNo, id) {
    document.getElementById('traceBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="small text-muted mt-2">Loading traceability timeline for ${outwardNo}...</div>
        </div>
    `;
    new bootstrap.Modal(document.getElementById('modalTraceability')).show();

    fetch('<?= \App\Core\Controller::url('/m/outward/traceability/') ?>' + id)
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

// Initialize batch filtering on load based on active current concern
document.addEventListener('DOMContentLoaded', function() {
    const srcCon = document.getElementById('sourceConcernSelect');
    if (srcCon && srcCon.value) {
        filterBatchesByConcern(srcCon.value);
    }
});
</script>
