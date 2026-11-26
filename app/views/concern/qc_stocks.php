<?php
// app/views/concern/qc_stocks.php
// Quality Control (QC) Inspections Register (Matches Image 1: /production/qc)
?>
<div class="container-fluid px-3 py-2">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small text-muted mb-0">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">QC Inspections</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center justify-content-center text-danger" style="width: 28px; height: 28px; border: 2px solid #ef4444; border-radius: 50%; font-size: 0.95rem;">
                    <i class="bi bi-patch-check"></i>
                </span>
                Quality Control (QC) Inspections
            </h4>
            <p class="text-secondary small mb-0">Multi-checkpoint inspection (stitching, fabric, color, size, trims) & finished goods approval</p>
        </div>
        <?php if (\App\Core\Auth::can('qc_stocks', 'create')): ?>
        <a href="<?= \App\Core\Controller::url('/production/qc/create') ?>" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm rounded-3" style="background:#4f46e5; border-color:#4f46e5;">
            <i class="bi bi-plus-circle me-1"></i> New QC Batch
        </a>
        <?php endif; ?>
    </div>

    <!-- Export Toolbar Card -->
    <?php
    $exportTitle = 'Export Quality Control (QC) Inspections (Excel, PDF, CSV)';
    $exportSubtitle = 'Download multi-checkpoint QC inspection records, pass/rework metrics & inspector details';
    $exportUrl = \App\Core\Controller::url('/production/qc/export');
    $currentModule = 'qc_stocks';
    $prefix = 'qc_exp';
    include dirname(__DIR__) . '/partials/export_card.php';
    ?>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url('/production/qc') ?>" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Inspection Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="PASSED" <?= ($statusFilter === 'PASSED') ? 'selected' : '' ?>>PASSED</option>
                        <option value="REWORK" <?= ($statusFilter === 'REWORK') ? 'selected' : '' ?>>REWORK</option>
                        <option value="REJECTED" <?= ($statusFilter === 'REJECTED') ? 'selected' : '' ?>>REJECTED</option>
                        <option value="COMPLETED" <?= ($statusFilter === 'COMPLETED') ? 'selected' : '' ?>>COMPLETED</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label small fw-semibold text-secondary mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="QC #, Program #, Product name, Inspector..." value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary btn-sm w-100 fw-semibold" style="background:#475569; border-color:#475569;">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inspections Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="font-size: 0.875rem;">
                <thead class="table-light text-muted text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                    <tr>
                        <th style="width: 14%;">QC BATCH ID</th>
                        <th style="width: 15%;">PROGRAM</th>
                        <th style="width: 18%;">PRODUCT</th>
                        <th style="width: 14%;">INSPECTOR</th>
                        <th style="width: 9%;">INSPECTED</th>
                        <th style="width: 8%;">PASSED</th>
                        <th style="width: 8%;">REJECTED</th>
                        <th style="width: 8%;">REWORK</th>
                        <th style="width: 9%;">STATUS</th>
                        <th style="width: 12%;" class="text-center">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inspections)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No quality control inspection records found for this concern.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inspections as $qc): ?>
                            <?php
                            $st = strtoupper(trim($qc['status'] ?? ''));
                            $statusBg = '#f1f5f9'; $statusColor = '#475569';
                            if ($st === 'REWORK') {
                                $statusBg = '#eab308'; $statusColor = '#ffffff';
                            } elseif ($st === 'PASSED' || $st === 'COMPLETED') {
                                $statusBg = '#10b981'; $statusColor = '#ffffff';
                            } elseif ($st === 'REJECTED') {
                                $statusBg = '#ef4444'; $statusColor = '#ffffff';
                            }
                            $reworkQty = (float)($qc['live_rework_qty'] ?? ($qc['rework_qty'] ?? 0));
                            $rejectedQty = (float)($qc['live_rejected_qty'] ?? ($qc['rejected_qty'] ?? 0));
                            $passedQty = (float)($qc['live_passed_qty'] ?? ($qc['passed_qty'] ?? 0));
                            $inspectedQty = (float)($qc['live_inspected_qty'] ?? ($qc['inspected_qty'] ?? ($passedQty + $reworkQty + $rejectedQty)));
                            ?>
                            <tr class="border-bottom">
                                <td class="py-3">
                                    <div class="fw-bold text-dark fs-6 font-monospace"><?= htmlspecialchars($qc['qc_no']) ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= !empty($qc['created_at']) ? date('d M Y', strtotime($qc['created_at'])) : date('d M Y') ?></small>
                                </td>
                                <td class="py-3">
                                    <span class="fw-semibold text-primary font-monospace"><?= htmlspecialchars($qc['program_no'] ?? '-') ?></span>
                                    <?php if (!empty($qc['stitching_batch_no'])): ?>
                                        <div class="small text-muted font-monospace" style="font-size:0.7rem;"><?= htmlspecialchars($qc['stitching_batch_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($qc['product_name'] ?? ($qc['variant_name'] ?? 'Production Garment')) ?></div>
                                    <?php if (!empty($qc['variant_count']) && $qc['variant_count'] > 1): ?>
                                        <span class="badge bg-light text-secondary border" style="font-size:0.7rem;"><?= (int)$qc['variant_count'] ?> variants in batch</span>
                                    <?php elseif (!empty($qc['product_code'])): ?>
                                        <small class="text-muted font-monospace" style="font-size: 0.72rem;"><?= htmlspecialchars($qc['product_code']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-secondary">
                                    <?= htmlspecialchars($qc['inspector_name'] ?? 'System Administrator') ?>
                                </td>
                                <td class="py-3">
                                    <span class="fw-bold text-dark"><?= number_format($inspectedQty) ?> pcs</span>
                                </td>
                                <td class="py-3">
                                    <span class="fw-bold text-success"><?= number_format($passedQty) ?> pcs</span>
                                </td>
                                <td class="py-3">
                                    <span class="fw-bold text-danger"><?= number_format($rejectedQty) ?> pcs</span>
                                </td>
                                <td class="py-3">
                                    <span class="fw-bold" style="color: #f59e0b;"><?= number_format($reworkQty) ?> pcs</span>
                                </td>
                                <td class="py-3">
                                    <span class="badge text-uppercase" style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; font-weight: 700; border-radius: 4px; padding: 4px 8px; font-size: 0.72rem; letter-spacing: 0.04em;">
                                        <?= htmlspecialchars($st ?: 'PENDING') ?>
                                    </span>
                                </td>
                                <td class="py-3 text-center">
                                    <div class="d-inline-flex align-items-center gap-1 justify-content-center">
                                        <!-- 1. View details link (cyan outline, opens /production/qc/details) -->
                                        <a href="<?= \App\Core\Controller::url('/production/qc/details?id=' . $qc['id']) ?>" class="btn btn-sm btn-outline-info rounded-2 p-1" style="width: 30px; height: 30px; border-color: #67e8f9; color: #0891b2; display:inline-flex; align-items:center; justify-content:center;" title="View Inspection Details (Record Details & Variant Table)">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- 2. Inspect / 2D Scanner icon -->
                                        <?php if (\App\Core\Auth::can('qc_stocks', 'edit')): ?>
                                        <a href="<?= \App\Core\Controller::url('/production/qc/inspect?id=' . $qc['id']) ?>" class="btn btn-sm btn-outline-primary rounded-2 p-1" style="width: 30px; height: 30px; border-color: #bfdbfe; color: #2563eb; display:inline-flex; align-items:center; justify-content:center;" title="Open 2D QR Piece Scanner">
                                            <i class="bi bi-qr-code-scan"></i>
                                        </a>

                                        <!-- 3. Re-inspect / Cycle icon (orange outline) -->
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-2 p-1" style="width: 30px; height: 30px; border-color: #fed7aa; color: #f97316; display:inline-flex; align-items:center; justify-content:center;" onclick='openReinspectModal(<?= json_encode($qc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Re-inspect / Cycle Rework Garments">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- 4. Delete QC record icon (red outline) -->
                                        <?php if (\App\Core\Auth::can('qc_stocks', 'delete')): ?>
                                        <form action="<?= \App\Core\Controller::url('/production/qc/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete QC inspection <?= htmlspecialchars($qc['qc_no']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= $qc['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-2 p-1" style="width: 30px; height: 30px; border-color: #fecaca; color: #dc2626; display:inline-flex; align-items:center; justify-content:center;" title="Delete QC Inspection">
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

        <!-- Footer Pagination -->
        <div class="card-footer bg-white py-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-muted">
                Showing <strong><?= count($inspections) ? (($page - 1) * $perPage + 1) : 0 ?></strong> to <strong><?= min($page * $perPage, $totalCount) ?></strong> of <strong><?= $totalCount ?></strong> inspections
                <span class="ms-3">Show: <strong><?= $perPage ?> per page</strong></span>
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= \App\Core\Controller::url('/production/qc?page=' . max(1, $page - 1) . '&status=' . urlencode($statusFilter ?? '') . '&search=' . urlencode($search ?? '')) ?>">&lt; Prev</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= \App\Core\Controller::url('/production/qc?page=' . $p . '&status=' . urlencode($statusFilter ?? '') . '&search=' . urlencode($search ?? '')) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= \App\Core\Controller::url('/production/qc?page=' . min($totalPages, $page + 1) . '&status=' . urlencode($statusFilter ?? '') . '&search=' . urlencode($search ?? '')) ?>">Next &gt;</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<?php if (\App\Core\Auth::can('qc_stocks', 'add')): ?>
<!-- Modal 1: New QC Batch -->
<div class="modal fade" id="modalAddQc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/qc/store') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
            <div class="modal-header text-white" style="background:#4f46e5;">
                <h6 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i> New QC Inspection Batch</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Product Variant *</label>
                    <select name="variant_id" class="form-select form-select-sm" required>
                        <option value="">-- Select Product Variant --</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['product_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Production Program (Optional)</label>
                    <select name="program_id" class="form-select form-select-sm">
                        <option value="">-- Direct Finished Piece --</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['id'] ?>"><?= htmlspecialchars($prog['program_no']) ?> - <?= htmlspecialchars($prog['program_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Inspected Qty *</label>
                        <input type="number" step="1" name="inspected_qty" id="inputInspectedQty" class="form-control form-control-sm" required placeholder="600" oninput="autoCalculatePassQty()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-success">Passed Qty *</label>
                        <input type="number" step="1" name="passed_qty" id="inputPassedQty" class="form-control form-control-sm" required placeholder="585">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-warning">Rework Qty</label>
                        <input type="number" step="1" name="rework_qty" id="inputReworkQty" class="form-control form-control-sm" value="0" oninput="autoCalculatePassQty()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-danger">Rejected Qty</label>
                        <input type="number" step="1" name="rejected_qty" id="inputRejectedQty" class="form-control form-control-sm" value="0" oninput="autoCalculatePassQty()">
                    </div>
                </div>
                <input type="hidden" name="location" value="Main Store">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Quality & Defect Observations</label>
                    <textarea name="defect_notes" class="form-control form-control-sm" rows="2" placeholder="Stitching neatness, measurements verified"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-3" style="background:#4f46e5; border-color:#4f46e5;">Save QC Inspection Batch</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal 2: View QC Details -->
<div class="modal fade" id="modalViewQc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-eye text-primary me-2"></i> Quality Control Inspection Breakdown</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">QC Batch ID</span>
                    <span class="font-monospace fw-bold fs-6 text-primary" id="view_qc_no">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Program</span>
                    <span class="badge bg-light text-dark border" id="view_qc_program">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Product</span>
                    <span class="fw-bold text-dark" id="view_qc_product">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Inspector</span>
                    <span class="fw-semibold text-secondary" id="view_qc_inspector">-</span>
                </div>
                <div class="row g-2 text-center my-3">
                    <div class="col-3">
                        <div class="p-2 bg-light rounded border">
                            <div class="text-muted" style="font-size:0.7rem;">Inspected</div>
                            <div class="fw-bold fs-6 text-dark" id="view_qc_inspected">0</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded border">
                            <div class="text-muted" style="font-size:0.7rem;">Passed</div>
                            <div class="fw-bold fs-6 text-success" id="view_qc_passed">0</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded border">
                            <div class="text-muted" style="font-size:0.7rem;">Rework</div>
                            <div class="fw-bold fs-6 text-warning" id="view_qc_rework">0</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded border">
                            <div class="text-muted" style="font-size:0.7rem;">Rejected</div>
                            <div class="fw-bold fs-6 text-danger" id="view_qc_rejected">0</div>
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <span class="text-muted small d-block mb-1">Defect Notes & Observations</span>
                    <div class="p-2.5 bg-light rounded text-dark small" id="view_qc_notes">-</div>
                </div>
                <div class="mt-2" id="view_rework_section" style="display:none;">
                    <span class="text-muted small d-block mb-1">Re-inspection History</span>
                    <pre class="p-2 bg-light rounded text-secondary small mb-0" id="view_qc_rework_notes" style="font-size:0.75rem; white-space: pre-wrap;"></pre>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (\App\Core\Auth::can('qc_stocks', 'edit')): ?>
<!-- Modal 3: Re-inspect / Cycle Rework Garments -->
<div class="modal fade" id="modalReinspectQc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/production/qc/reinspect') ?>" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="id" id="reinspect_qc_id" value="">
            <div class="modal-header bg-warning text-dark">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-1"></i> Re-inspect / Cycle Rework Garments</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning py-2 small mb-3">
                    <strong>Batch:</strong> <span id="reinspect_qc_no" class="font-monospace fw-bold">-</span> | 
                    <strong>Current in Rework:</strong> <span id="reinspect_avail_qty" class="fw-bold">0</span> pcs
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-success">Approved / Passed from Rework *</label>
                        <input type="number" step="1" name="passed_add" id="reinspect_passed_add" class="form-control form-control-sm" value="0" min="0" required>
                        <div class="form-text small text-muted">Repairs approved, moves to passed available stock.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-danger">Permanently Rejected *</label>
                        <input type="number" step="1" name="rejected_add" id="reinspect_rejected_add" class="form-control form-control-sm" value="0" min="0" required>
                        <div class="form-text small text-muted">Irreparable defects, discarded from batch.</div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Re-inspection Notes</label>
                    <textarea name="rework_notes" class="form-control form-control-sm" rows="2" placeholder="Repairs verified: collar re-stitched, buttons re-aligned"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning btn-sm px-3 fw-semibold">Apply Re-inspection Cycle</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function autoCalculatePassQty() {
    var inspected = parseFloat(document.getElementById('inputInspectedQty').value) || 0;
    var rework = parseFloat(document.getElementById('inputReworkQty').value) || 0;
    var rejected = parseFloat(document.getElementById('inputRejectedQty').value) || 0;
    var passed = Math.max(0, inspected - (rework + rejected));
    document.getElementById('inputPassedQty').value = passed;
}

function openViewQcModal(qc) {
    if (!qc) return;
    document.getElementById('view_qc_no').innerText = qc.qc_no || '-';
    document.getElementById('view_qc_program').innerText = qc.program_no || '-';
    document.getElementById('view_qc_product').innerText = qc.variant_name || '-';
    document.getElementById('view_qc_inspector').innerText = qc.inspector_name || 'System Administrator';
    document.getElementById('view_qc_inspected').innerText = parseInt(qc.inspected_qty || 0) + ' pcs';
    document.getElementById('view_qc_passed').innerText = parseInt(qc.passed_qty || 0) + ' pcs';
    document.getElementById('view_qc_rework').innerText = parseInt(qc.rework_qty || 0) + ' pcs';
    document.getElementById('view_qc_rejected').innerText = parseInt(qc.rejected_qty || 0) + ' pcs';
    document.getElementById('view_qc_notes').innerText = qc.defect_notes || 'No defect notes recorded.';

    var reworkSec = document.getElementById('view_rework_section');
    if (qc.rework_notes && qc.rework_notes.trim() !== '') {
        document.getElementById('view_qc_rework_notes').innerText = qc.rework_notes;
        reworkSec.style.display = 'block';
    } else {
        reworkSec.style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('modalViewQc')).show();
}

function openReinspectModal(qc) {
    if (!qc) return;
    var reworkQty = parseFloat(qc.rework_qty || 0);
    if (reworkQty <= 0) {
        alert("This batch has 0 garments in the rework queue.");
        return;
    }
    document.getElementById('reinspect_qc_id').value = qc.id;
    document.getElementById('reinspect_qc_no').innerText = qc.qc_no || '-';
    document.getElementById('reinspect_avail_qty').innerText = reworkQty;
    document.getElementById('reinspect_passed_add').value = reworkQty;
    document.getElementById('reinspect_rejected_add').value = 0;
    document.getElementById('reinspect_passed_add').max = reworkQty;
    document.getElementById('reinspect_rejected_add').max = reworkQty;

    new bootstrap.Modal(document.getElementById('modalReinspectQc')).show();
}
</script>
