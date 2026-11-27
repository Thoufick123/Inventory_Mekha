<?php
// app/views/concern/qc_details.php
// Quality Control Record Details & Inspection Items (Matches Screenshot 2)
?>
<div class="container-fluid px-3 py-2">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small text-muted mb-0">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="text-decoration-none text-muted">Quality Control</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Details</li>
        </ol>
    </nav>

    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-primary"></i> <?= htmlspecialchars($qc['qc_no']) ?> Details
            </h5>
            <div class="text-muted small">Read-only record details &amp; finished stock approval audit</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to QC List
            </a>
            <?php if (\App\Core\Auth::can('qc_stocks', 'edit')): ?>
            <a href="<?= \App\Core\Controller::url('/production/qc/inspect?id=' . (int)$qc['id']) ?>" class="btn btn-outline-primary btn-sm px-3 fw-semibold">
                <i class="bi bi-qr-code-scan me-1"></i> Open QR Scanner
            </a>
            <?php endif; ?>
            <a href="<?= \App\Core\Controller::url('/m/stocks?tab=live_stock') ?>" class="btn btn-success btn-sm px-3 fw-semibold">
                <i class="bi bi-box-seam me-1"></i> View Live Stock
            </a>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- RECORD DETAILS CARD (Matches Image 2)      -->
    <!-- ========================================== -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark">Record Details</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-4 col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Program</div>
                    <div class="fw-bold text-dark fs-6 mt-1 font-monospace">
                        <?= htmlspecialchars($qc['program_no'] ?: 'PRG-GEN') ?>
                    </div>
                </div>
                <div class="col-md-5 col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Product</div>
                    <div class="fw-bold text-dark fs-6 mt-1">
                        <?= htmlspecialchars($qc['catalog_name'] ?: ($qc['variant_name'] ?: ($qc['program_name'] ?: 'Garment Product'))) ?>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Inspector</div>
                    <div class="fw-bold text-dark fs-6 mt-1">
                        <?= htmlspecialchars($qc['inspector_name'] ?: 'System Administrator') ?>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Status</div>
                    <div class="mt-1">
                        <?php
                        $st = strtoupper(trim($qc['status'] ?? ''));
                        $bg = 'bg-secondary';
                        if ($st === 'PASSED' || $st === 'COMPLETED') $bg = 'bg-success';
                        elseif ($st === 'REWORK') $bg = 'bg-warning text-dark';
                        elseif ($st === 'PARTIAL') $bg = 'bg-primary';
                        elseif ($st === 'REJECTED') $bg = 'bg-danger';
                        ?>
                        <span class="badge <?= $bg ?> px-3 py-1 font-monospace fw-bold fs-6">
                            <?= htmlspecialchars($st) ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Total Quantity</div>
                    <div class="fw-bold text-dark fs-5 mt-1 font-monospace">
                        <?= (int)$qc['inspected_qty'] ?>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Passed</div>
                    <div class="fw-bold text-success fs-5 mt-1 font-monospace">
                        <?= (int)$qc['passed_qty'] ?>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Rejected</div>
                    <div class="fw-bold text-danger fs-5 mt-1 font-monospace">
                        <?= (int)$qc['rejected_qty'] ?>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Rework</div>
                    <div class="fw-bold text-warning-emphasis fs-5 mt-1 font-monospace">
                        <?= (int)$qc['rework_qty'] ?>
                    </div>
                </div>

                <div class="col-12">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.75rem;">Created At</div>
                    <div class="text-muted small mt-1 font-monospace">
                        <?= date('Y-m-d H:i:s', strtotime($qc['created_at'])) ?>
                        <?php if ($qc['defect_notes']): ?>
                            &bull; <strong>Inspection Notes:</strong> <?= htmlspecialchars($qc['defect_notes']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- REWORK RE-INSPECTION BANNER (If Rework exists) -->
    <?php if ((float)$qc['rework_qty'] > 0): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                    Garments Pending Rework &amp; Repair (<?= (int)$qc['rework_qty'] ?> pcs)
                </h6>
                <div class="small text-muted">
                    When repaired by the tailor, re-inspect these items here to credit newly passed garments directly to Live Stock.
                </div>
            </div>
            <?php if (\App\Core\Auth::can('qc_stocks', 'edit') || \App\Core\Auth::can('qc_stocks', 'add')): ?>
            <button type="button" class="btn btn-dark fw-semibold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalReinspect">
                <i class="bi bi-arrow-repeat me-1"></i> Re-inspect Rework Garments
            </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- INSPECTION ITEMS TABLE (Matches Image 2)   -->
    <!-- ========================================== -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">Inspection Items</h6>
            <span class="badge bg-light text-secondary border"><?= count($items) ?> Variants</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                    <thead class="table-light text-uppercase small text-secondary" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                        <tr>
                            <th class="ps-4 py-2.5">CATALOG</th>
                            <th class="text-center py-2.5" style="width: 120px;">SIZE</th>
                            <th class="text-center py-2.5" style="width: 120px;">COLOR</th>
                            <th class="text-center py-2.5" style="width: 130px;">INSPECTED</th>
                            <th class="text-center py-2.5" style="width: 130px;">PASSED</th>
                            <th class="text-center py-2.5" style="width: 130px;">REJECTED</th>
                            <th class="text-center pe-4 py-2.5" style="width: 130px;">REWORK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No variant item breakdown recorded.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars($it['hierarchy_path'] ?: ($it['catalog_name'] ?: 'Production Catalog')) ?>
                                        </div>
                                        <?php if (!empty($it['variant_id']) || !empty($it['product_code'])): ?>
                                            <span class="badge bg-light text-secondary border font-monospace mt-1" style="font-size:0.7rem;">
                                                Variant ID: <?= htmlspecialchars(\App\Services\QrService::formatBrandVariantCode((int)($it['variant_id'] ?? 0)) ?: (string)($it['product_code'] ?? '')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center py-3">
                                        <span class="badge bg-dark px-2.5 py-1 font-monospace">
                                            <?= htmlspecialchars($it['size'] ?: 'Free') ?>
                                        </span>
                                    </td>
                                    <td class="text-center py-3">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 font-monospace">
                                            <?= htmlspecialchars($it['color'] ?: 'STD') ?>
                                        </span>
                                    </td>
                                    <td class="text-center py-3 fw-bold text-dark font-monospace fs-6">
                                        <?= (int)$it['inspected_qty'] ?>
                                    </td>
                                    <td class="text-center py-3 fw-bold text-success font-monospace fs-6">
                                        <?= (int)$it['passed_qty'] ?>
                                    </td>
                                    <td class="text-center py-3 fw-bold text-danger font-monospace fs-6">
                                        <?= (int)$it['rejected_qty'] ?>
                                    </td>
                                    <td class="text-center pe-4 py-3 fw-bold text-warning-emphasis font-monospace fs-6">
                                        <?= (int)$it['rework_qty'] ?>
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

<?php if (\App\Core\Auth::can('qc_stocks', 'edit')): ?>
<!-- MODAL: RE-INSPECT REWORK -->
<div class="modal fade" id="modalReinspect" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/production/qc/reinspect') ?>" method="POST" class="modal-content border-0 shadow rounded-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$qc['id'] ?>">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-arrow-repeat text-warning me-1"></i> Re-inspect Rework Pieces
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border small text-muted mb-3">
                    Currently in Rework: <strong class="text-dark font-monospace"><?= (int)$qc['rework_qty'] ?> pcs</strong>.
                    Any newly passed pieces will immediately enter Live Stock.
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-success">Passed After Repair (Pcs) *</label>
                        <input type="number" name="passed_add" class="form-control fw-bold text-success" min="0" max="<?= (int)$qc['rework_qty'] ?>" value="<?= (int)$qc['rework_qty'] ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-danger">Permanently Rejected (Pcs)</label>
                        <input type="number" name="rejected_add" class="form-control fw-bold text-danger" min="0" max="<?= (int)$qc['rework_qty'] ?>" value="0">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold text-secondary">Repair &amp; Re-inspection Notes</label>
                    <textarea name="rework_notes" class="form-control" rows="3" placeholder="Repairs completed, seam re-stitched, etc..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-top py-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success fw-semibold px-4">
                    <i class="bi bi-check2-circle me-1"></i> Pass to Live Stock
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
