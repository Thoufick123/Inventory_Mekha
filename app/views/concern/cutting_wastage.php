<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Fabric Cutting & Wastage Tracking</h4>
            <p class="text-secondary small mb-0">Record fabric lay cutting, cut piece generation, and scrap / edge wastage. Consumes raw fabric from live stock via <code>StockService::move()</code>.</p>
        </div>
        <?php if (\App\Core\Auth::can('cutting_wastage', 'add')): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddCutting">
            <i class="bi bi-scissors me-1"></i> Log Cutting Job
        </button>
        <?php endif; ?>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Cutting & Wastage Logs (Excel & PDF)';
    $exportSubtitle = 'Download fabric meterage consumed, pattern pieces cut, scrap generation & yield metrics';
    $exportUrl = \App\Core\Controller::url('/m/cutting_wastage/export');
    $prefix = 'cut_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-scissors me-2 text-primary"></i> Cutting Operations Register</h6>
            <span class="badge bg-light text-dark border"><?= count($logs) ?> Jobs</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Log No</th>
                        <th>Program Ref</th>
                        <th>Raw Fabric Consumed</th>
                        <th>Cut Pieces Generated</th>
                        <th>Wastage Scrap</th>
                        <th>Wastage %</th>
                        <th>Date & Time</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No cutting jobs recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $cl): 
                            $pct = $cl['consumed_qty'] > 0 ? ($cl['wastage_qty'] / $cl['consumed_qty']) * 100 : 0;
                        ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($cl['log_no']) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($cl['program_no'] ?? 'Unscheduled') ?></span></td>
                                <td>
                                    <div class="fw-bold text-danger">-<?= number_format($cl['consumed_qty'], 2) ?> <?= htmlspecialchars($cl['wastage_unit']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($cl['raw_variant_name']) ?></small>
                                </td>
                                <td><span class="fw-bold text-success fs-6"><?= number_format($cl['cut_pieces']) ?> Cut Panels</span></td>
                                <td><span class="text-danger fw-semibold"><?= number_format($cl['wastage_qty'], 2) ?> <?= htmlspecialchars($cl['wastage_unit']) ?></span></td>
                                <td>
                                    <span class="badge <?= $pct > 5 ? 'bg-danger' : 'bg-success' ?>">
                                        <?= number_format($pct, 1) ?>%
                                    </span>
                                </td>
                                <td class="small text-muted font-monospace"><?= date('d M Y H:i', strtotime($cl['created_at'])) ?></td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewCuttingModal(<?= json_encode($cl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Cutting Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditCuttingModal(<?= json_encode($cl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Cutting Counts">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="<?= \App\Core\Controller::url('/m/cutting_wastage/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete cutting log <?= htmlspecialchars($cl['log_no']) ?>? Consumed raw stock will be restored.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $cl['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Cutting Log">
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

<div class="modal fade" id="modalAddCutting" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/cutting_wastage/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Log Fabric Cutting Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Production Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">Ad-hoc / Manual Cutting Job</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['id'] ?>"><?= htmlspecialchars($prog['program_no']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Raw Fabric Variant (Deducts Stock) *</label>
                    <select name="raw_variant_id" class="form-select" required>
                        <option value="">Select Fabric Variant</option>
                        <?php foreach ($rawVariants as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['sku_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Fabric Consumed (kg/m) *</label>
                        <input type="number" step="0.01" name="consumed_qty" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Panels / Cut Pieces Output *</label>
                        <input type="number" name="cut_pieces" class="form-control" required placeholder="e.g. 250">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Fabric Wastage Scrap (kg)</label>
                    <input type="number" step="0.01" name="wastage_qty" class="form-control" value="0.00" placeholder="0.00">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Cutting Bay Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Lay height, marker efficiency"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Deduct Fabric & Save Cutting Log</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Cutting -->
<div class="modal fade" id="modalEditCutting" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/cutting_wastage/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_cutting_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Cutting Counts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Log No</label>
                    <input type="text" id="edit_cutting_no" class="form-control font-monospace" readonly>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Cut Pieces Generated *</label>
                        <input type="number" name="cut_pieces" id="edit_cut_pieces" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Wastage Scrap (kg)</label>
                        <input type="number" step="0.01" name="wastage_qty" id="edit_cut_wastage" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Cutting Bay Notes</label>
                    <textarea name="notes" id="edit_cutting_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Cutting Log</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Cutting Log -->
<div class="modal fade" id="modalViewCutting" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye text-info me-2"></i> Cutting Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Log / Job No</span>
                    <span class="font-monospace fw-bold fs-6 text-primary" id="view_cutting_no">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Program</span>
                    <span class="badge bg-light text-dark border" id="view_cutting_program">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Raw Material Consumed</span>
                    <span class="fw-semibold text-danger" id="view_cutting_raw">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Cut Pieces / Panels</span>
                    <span class="fw-bold fs-6 text-success" id="view_cutting_cut">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Wastage / Scrap</span>
                    <span class="fw-semibold text-danger" id="view_cutting_waste">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Date & Time</span>
                    <span class="font-monospace text-secondary small" id="view_cutting_date">-</span>
                </div>
                <div class="mb-2">
                    <span class="text-muted small d-block mb-1">Cutting Bay Notes</span>
                    <div class="p-2 bg-light rounded text-dark small" id="view_cutting_notes">-</div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openViewCuttingModal(cl) {
    if (!cl) return;
    document.getElementById('view_cutting_no').innerText = cl.log_no || '-';
    document.getElementById('view_cutting_program').innerText = cl.program_no || 'Ad-hoc Job';
    document.getElementById('view_cutting_raw').innerText = '-' + (parseFloat(cl.consumed_qty || 0).toLocaleString()) + ' ' + (cl.wastage_unit || '') + ' (' + (cl.raw_variant_name || '-') + ')';
    document.getElementById('view_cutting_cut').innerText = (parseInt(cl.cut_pieces || 0).toLocaleString()) + ' Panels';
    document.getElementById('view_cutting_waste').innerText = (parseFloat(cl.wastage_qty || 0).toFixed(2)) + ' ' + (cl.wastage_unit || '');
    document.getElementById('view_cutting_date').innerText = cl.created_at || '-';
    document.getElementById('view_cutting_notes').innerText = cl.notes || 'No notes entered.';

    const modal = new bootstrap.Modal(document.getElementById('modalViewCutting'));
    modal.show();
}

function openEditCuttingModal(cl) {
    if (!cl) return;
    document.getElementById('edit_cutting_id').value = cl.id || '';
    document.getElementById('edit_cutting_no').value = cl.log_no || '';
    document.getElementById('edit_cut_pieces').value = cl.cut_pieces || 0;
    document.getElementById('edit_cut_wastage').value = parseFloat(cl.wastage_qty || 0).toFixed(2);
    document.getElementById('edit_cutting_notes').value = cl.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditCutting'));
    modal.show();
}
</script>
