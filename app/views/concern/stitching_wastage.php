<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Stitching & Assembly Tracking</h4>
            <p class="text-secondary small mb-0">Record garment assembly, sewing line output, and needle breakage / stitching reject wastage.</p>
        </div>
        <?php if (\App\Core\Auth::can('stitching_wastage', 'add')): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddStitching">
            <i class="bi bi-layers me-1"></i> Log Stitching Batch
        </button>
        <?php endif; ?>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Stitching Production Logs (Excel & PDF)';
    $exportSubtitle = 'Download assembly outputs, completed garments, line defect rates & operator efficiency audits';
    $exportUrl = \App\Core\Controller::url('/m/stitching_wastage/export');
    $prefix = 'stitch_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-layers me-2 text-primary"></i> Sewing Floor Output</h6>
            <span class="badge bg-light text-dark border"><?= count($logs) ?> Runs</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Log No</th>
                        <th>Program Ref</th>
                        <th>Target Garment</th>
                        <th>Input Cut Panels</th>
                        <th>Stitched Garments</th>
                        <th>Line Rejects (Wastage)</th>
                        <th>Date & Time</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No stitching batches logged.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $sl): ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($sl['log_no']) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($sl['program_no'] ?? 'Direct') ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($sl['produced_variant_name']) ?></div>
                                    <small class="badge bg-light text-dark border"><?= htmlspecialchars($sl['product_code']) ?></small>
                                </td>
                                <td><?= number_format($sl['input_pieces']) ?> Panels</td>
                                <td><span class="fw-bold text-success fs-6"><?= number_format($sl['stitched_pieces']) ?> Pcs</span></td>
                                <td>
                                    <?php if ($sl['wastage_pieces'] > 0): ?>
                                        <span class="text-danger fw-semibold"><?= number_format($sl['wastage_pieces']) ?> Defective</span>
                                    <?php else: ?>
                                        <span class="text-success small">Zero Defect</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted font-monospace"><?= date('d M Y H:i', strtotime($sl['created_at'])) ?></td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewStitchingModal(<?= json_encode($sl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Stitching Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditStitchingModal(<?= json_encode($sl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Counts">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="<?= \App\Core\Controller::url('/m/stitching_wastage/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete stitching record <?= htmlspecialchars($sl['log_no']) ?>? Stock additions will be reversed.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $sl['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Stitching Log">
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

<div class="modal fade" id="modalAddStitching" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/stitching_wastage/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Log Stitching Assembly</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Production Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">Ad-hoc Line Production</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['id'] ?>"><?= htmlspecialchars($prog['program_no']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Finished Garment Variant *</label>
                    <select name="produced_variant_id" class="form-select" required>
                        <option value="">Select Target Garment</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['product_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Input Cut Panels *</label>
                        <input type="number" name="input_pieces" class="form-control" required placeholder="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Stitched Units *</label>
                        <input type="number" name="stitched_pieces" class="form-control" required placeholder="98">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Reject Wastage</label>
                        <input type="number" name="wastage_pieces" class="form-control" value="0" placeholder="2">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notes / Stitching Line</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Line 1 / Machine operator ref"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Stitching Output</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Stitching -->
<div class="modal fade" id="modalEditStitching" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/stitching_wastage/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_stitching_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Stitching Output</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Log No</label>
                    <input type="text" id="edit_stitching_no" class="form-control font-monospace" readonly>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Stitched Pcs Completed *</label>
                        <input type="number" name="stitched_pieces" id="edit_stitched_pieces" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Reject Wastage Pieces</label>
                        <input type="number" name="wastage_pieces" id="edit_stitching_wastage" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notes / Stitching Line</label>
                    <textarea name="notes" id="edit_stitching_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Stitching Log</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Stitching Log -->
<div class="modal fade" id="modalViewStitching" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye text-info me-2"></i> Stitching Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Log No</span>
                    <span class="font-monospace fw-bold fs-6 text-primary" id="view_stitching_no">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Program</span>
                    <span class="badge bg-light text-dark border" id="view_stitching_program">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Product SKU</span>
                    <span class="fw-semibold text-dark" id="view_stitching_product">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Input Panels</span>
                    <span class="fw-bold fs-6 text-secondary" id="view_stitching_input">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Stitched Pieces (Good)</span>
                    <span class="fw-bold fs-6 text-success" id="view_stitching_stitched">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Defective / Wastage</span>
                    <span class="fw-bold fs-6 text-danger" id="view_stitching_waste">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Date & Time</span>
                    <span class="font-monospace text-secondary small" id="view_stitching_date">-</span>
                </div>
                <div class="mb-2">
                    <span class="text-muted small d-block mb-1">Notes / Stitching Line</span>
                    <div class="p-2 bg-light rounded text-dark small" id="view_stitching_notes">-</div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openViewStitchingModal(sl) {
    if (!sl) return;
    document.getElementById('view_stitching_no').innerText = sl.log_no || '-';
    document.getElementById('view_stitching_program').innerText = sl.program_no || 'Ad-hoc Job';
    document.getElementById('view_stitching_product').innerText = (sl.product_code ? sl.product_code + ' - ' : '') + (sl.variant_name || '-');
    document.getElementById('view_stitching_input').innerText = (parseInt(sl.input_pieces || 0).toLocaleString()) + ' Panels';
    document.getElementById('view_stitching_stitched').innerText = (parseInt(sl.stitched_pieces || 0).toLocaleString()) + ' Pcs';
    document.getElementById('view_stitching_waste').innerText = (parseInt(sl.wastage_pieces || 0).toLocaleString()) + ' Pcs';
    document.getElementById('view_stitching_date').innerText = sl.created_at || '-';
    document.getElementById('view_stitching_notes').innerText = sl.notes || 'No notes entered.';

    const modal = new bootstrap.Modal(document.getElementById('modalViewStitching'));
    modal.show();
}

function openEditStitchingModal(sl) {
    if (!sl) return;
    document.getElementById('edit_stitching_id').value = sl.id || '';
    document.getElementById('edit_stitching_no').value = sl.log_no || '';
    document.getElementById('edit_stitched_pieces').value = sl.stitched_pieces || 0;
    document.getElementById('edit_stitching_wastage').value = sl.wastage_pieces || 0;
    document.getElementById('edit_stitching_notes').value = sl.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditStitching'));
    modal.show();
}
</script>
