<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Production Batch & Lot Tracking</h4>
            <p class="text-secondary small mb-0">Manage production lots, manufacturing and expiry dates per variant.</p>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddBatch">
            <i class="bi bi-collection me-1"></i> Initialize Batch
        </button>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Batch Lots (Excel & PDF)';
    $exportSubtitle = 'Download manufacturing lots, expiry schedules, variant quantities & batch lifecycle tracking';
    $exportUrl = \App\Core\Controller::url('/m/batch/export');
    $prefix = 'batch_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-collection me-2 text-primary"></i> Active Batches</h6>
            <span class="badge bg-light text-dark border"><?= count($batches) ?> Batches</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Batch No</th>
                        <th>Variant</th>
                        <th>Batch Size / Qty</th>
                        <th>Manufacturing Date</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No batches registered.</td></tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($b['batch_no']) ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($b['variant_name']) ?></div>
                                    <small class="badge bg-light text-dark border"><?= htmlspecialchars($b['sku_code']) ?></small>
                                </td>
                                <td><span class="fw-bold text-primary"><?= number_format($b['batch_qty'], 2) ?> <?= htmlspecialchars($b['weight_unit']) ?></span></td>
                                <td><?= date('d M Y', strtotime($b['mfg_date'])) ?></td>
                                <td><?= $b['exp_date'] ? date('d M Y', strtotime($b['exp_date'])) : '<span class="text-muted">None</span>' ?></td>
                                <td>
                                    <?php 
                                        $st = strtoupper($b['status'] ?? 'ACTIVE');
                                        if ($st === 'DRAFT'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Draft</span>
                                    <?php elseif ($st === 'APPROVED'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                    <?php elseif ($st === 'DISPATCHED'): ?>
                                        <span class="badge bg-info text-dark"><i class="bi bi-truck me-1"></i>Dispatched</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= ucfirst(strtolower($st)) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($b['total_boxes'])): ?>
                                        <small class="d-block text-muted mt-0.5"><?= (int)$b['total_boxes'] ?> Boxes</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <?php if (strtoupper($b['status'] ?? '') === 'DRAFT'): ?>
                                            <form action="<?= \App\Core\Controller::url('/m/batch/approve') ?>" method="POST" class="d-inline" onsubmit="return confirm('Supervisor Approval: Approve batch <?= htmlspecialchars($b['batch_no']) ?> and credit live inventory?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success shadow-sm" title="Supervisor Approve Batch">
                                                    <i class="bi bi-check2-circle me-1"></i> Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewBatchModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Batch Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditBatchModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Batch">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="<?= \App\Core\Controller::url('/m/batch/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete batch <?= htmlspecialchars($b['batch_no']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Batch">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAddBatch" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/batch/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-seam me-1 text-primary"></i> Create Packaging Batch (PB-XXXXX)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($productionUnits)): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Production Unit</label>
                        <select name="production_unit_id" class="form-select form-select-sm">
                            <option value="">-- Main Facility Unit --</option>
                            <?php foreach ($productionUnits as $pu): ?>
                                <option value="<?= $pu['id'] ?>"><?= htmlspecialchars($pu['name']) ?> (<?= htmlspecialchars($pu['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (!empty($qcInspections)): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Link Passed QC Inspection Batch (Optional)</label>
                        <select name="qc_batch_id" class="form-select form-select-sm">
                            <option value="">-- Manual Packaging (No QC Reference) --</option>
                            <?php foreach ($qcInspections as $qc): ?>
                                <option value="<?= $qc['id'] ?>">QC #<?= htmlspecialchars($qc['qc_no']) ?> &bull; Passed: <?= number_format($qc['passed_qty'], 2) ?> pcs</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Product Variant *</label>
                    <select name="variant_id" class="form-select" required>
                        <option value="">Select Variant</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['sku_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Packaged Garment Qty (Pcs) *</label>
                        <input type="number" step="0.01" name="batch_qty" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Master Cartons / Boxes</label>
                        <input type="number" name="total_boxes" class="form-control" placeholder="e.g. 5">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Mfg Date</label>
                        <input type="date" name="mfg_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Exp Date</label>
                        <input type="date" name="exp_date" class="form-control">
                    </div>
                </div>
                <div class="p-2 bg-light border rounded small text-muted">
                    <i class="bi bi-info-circle text-primary me-1"></i> Batches are initialized as <strong>DRAFT</strong>. Once packed, a supervisor approves the batch to capitalize finished goods into Available Stock.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Packaging Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Batch -->
<div class="modal fade" id="modalEditBatch" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/batch/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_batch_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Production Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Batch No</label>
                    <input type="text" id="edit_batch_no" class="form-control font-monospace" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Batch Size / Qty *</label>
                    <input type="number" step="0.01" name="batch_qty" id="edit_batch_qty" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Mfg Date</label>
                        <input type="date" name="mfg_date" id="edit_mfg_date" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Exp Date</label>
                        <input type="date" name="exp_date" id="edit_exp_date" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Status *</label>
                    <select name="status" id="edit_batch_status" class="form-select" required>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Batch -->
<div class="modal fade" id="modalViewBatch" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-box-seam"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Production Batch Details</h6>
                        <small class="badge bg-dark font-monospace" id="view_batch_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Batch Planned Size</span>
                        <span class="fw-bold fs-5 text-primary font-monospace" id="view_batch_qty">0</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Batch Status</span>
                        <span class="badge" id="view_batch_status_badge">Active</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-tag text-primary me-2"></i>Variant Style:</span>
                        <strong class="text-dark" id="view_batch_variant">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-upc-scan text-primary me-2"></i>SKU Code:</span>
                        <span class="badge bg-light text-dark border font-monospace" id="view_batch_sku">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-check text-primary me-2"></i>Mfg Date:</span>
                        <span class="font-monospace text-dark" id="view_batch_mfg">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-x text-primary me-2"></i>Expiry Date:</span>
                        <span class="font-monospace text-dark" id="view_batch_exp">None</span>
                    </li>
                </ul>
            </div>
            <div class="modal-footer border-top px-4 py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openViewBatchModal(b) {
    if (!b) return;
    document.getElementById('view_batch_no').innerText = b.batch_no || '';
    document.getElementById('view_batch_variant').innerText = b.variant_name || '-';
    document.getElementById('view_batch_sku').innerText = b.sku_code || '-';
    document.getElementById('view_batch_qty').innerText = parseFloat(b.batch_qty || 0).toLocaleString('en-IN') + ' ' + (b.weight_unit || 'pcs');
    document.getElementById('view_batch_mfg').innerText = b.mfg_date || '-';
    document.getElementById('view_batch_exp').innerText = b.exp_date || 'None';

    const statusBadge = document.getElementById('view_batch_status_badge');
    if (b.status === 'completed') {
        statusBadge.className = 'badge bg-success';
        statusBadge.innerText = 'Completed';
    } else if (b.status === 'active') {
        statusBadge.className = 'badge bg-primary';
        statusBadge.innerText = 'Active';
    } else {
        statusBadge.className = 'badge bg-secondary';
        statusBadge.innerText = 'Closed';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalViewBatch'));
    modal.show();
}
function openEditBatchModal(b) {
    if (!b) return;
    document.getElementById('edit_batch_id').value = b.id || '';
    document.getElementById('edit_batch_no').value = b.batch_no || '';
    document.getElementById('edit_batch_qty').value = parseFloat(b.batch_qty || 0).toFixed(2);
    document.getElementById('edit_mfg_date').value = b.mfg_date ? b.mfg_date.substring(0, 10) : '';
    document.getElementById('edit_exp_date').value = b.exp_date ? b.exp_date.substring(0, 10) : '';
    document.getElementById('edit_batch_status').value = b.status || 'scheduled';

    const modal = new bootstrap.Modal(document.getElementById('modalEditBatch'));
    modal.show();
}
</script>
