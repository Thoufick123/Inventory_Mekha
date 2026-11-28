<?php
// app/views/concern/qc_create.php
// Initialize QC Inspection Batch (Matches Screenshot 3)
$savedQc = $savedQc ?? null;
$qcPassedTotal = 0;
$qcRejectedTotal = 0;
$qcReworkTotal = 0;
foreach ($batchItems as $qcBatchItem) {
    $qcPassedTotal += (int)($qcBatchItem['qc_passed_qty'] ?? 0);
    $qcRejectedTotal += (int)($qcBatchItem['qc_rejected_qty'] ?? 0);
    $qcReworkTotal += (int)($qcBatchItem['qc_rework_qty'] ?? 0);
}
?>
<style>
.qc-create-table-format {
    border-collapse: collapse;
    font-size: 0.84rem;
}
.qc-create-table-format thead th {
    background-color: #f1f5f9 !important;
    color: #475569 !important;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.65rem 0.75rem;
    border-bottom: 2px solid #cbd5e1 !important;
    white-space: nowrap;
}
.qc-create-table-format tbody td {
    padding: 0.55rem 0.75rem;
    border-color: #e2e8f0 !important;
    vertical-align: middle;
    white-space: nowrap;
}
.qc-create-table-format tbody tr:hover {
    background-color: #f8fafc;
}
</style>
<div class="container-fluid px-3 pt-1 pb-3">

    <form action="<?= \App\Core\Controller::url('/production/qc/store') ?>" method="POST" id="formQcCreate">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="submit_action" id="submitActionInput" value="save_and_inspect">
        <?php if ($savedQc): ?>
            <input type="hidden" name="qc_id" id="savedQcId" value="<?= (int)$savedQc['id'] ?>">
        <?php endif; ?>

        <div class="row g-4">
            <!-- ========================================== -->
            <!-- LEFT COLUMN: QC BATCH INFO (Matches Image 3) -->
            <!-- ========================================== -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <h6 class="mb-0 fw-bold text-dark">QC Batch Info</h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Select Source Batch * -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">
                                Select Source Batch <span class="text-danger">*</span>
                            </label>
                            <select name="stitching_batch_id" id="selectStitchingBatch" class="form-select border-light-subtle shadow-sm" required onchange="onStitchingBatchChange(this.value)" <?= $savedQc ? 'disabled' : '' ?>>
                                <option value="">-- Choose Stitching Batch --</option>
                                <?php foreach ($stitchingBatches as $sb): ?>
                                    <option value="<?= $sb['id'] ?>"
                                            data-concern="<?= (int)$sb['concern_id'] ?>"
                                            data-no="<?= htmlspecialchars($sb['display_batch_no']) ?>"
                                            data-program="<?= htmlspecialchars(($sb['program_no'] ?? 'PRG') . ' — ' . ($sb['program_name'] ?? 'Production Program')) ?>"
                                            data-pieces="<?= (int)$sb['total_pieces'] ?>"
                                            data-variants="<?= (int)$sb['variant_count'] ?>"
                                            <?= ($selectedStitchingId == $sb['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sb['display_batch_no']) ?> • <?= htmlspecialchars(($sb['program_no'] ?? 'PRG') . ' - ' . ($sb['program_name'] ?: 'Garments')) ?> • <?= (int)$sb['total_pieces'] ?> pcs / <?= (int)$sb['variant_count'] ?> variants
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- QC Inspector -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold text-secondary mb-0">
                                    <i class="bi bi-person-badge text-primary me-1"></i> QC Inspector
                                </label>
                                <?php if (!$savedQc): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalAddQcStaff">
                                    <i class="bi bi-plus"></i> Add Staff
                                </button>
                                <?php endif; ?>
                            </div>
                            <select name="inspector_id" id="selectInspector" class="form-select border-light-subtle shadow-sm" required <?= $savedQc ? 'disabled' : '' ?>>
                                <?php foreach ($inspectors as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($u['id'] == \App\Core\Auth::id()) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['name'] . ($u['role'] ? ' (' . ucfirst($u['role']) . ')' : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- General Inspection Notes -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">General Inspection Notes</label>
                            <textarea name="notes" class="form-control border-light-subtle shadow-sm" rows="3" 
                                      placeholder="Fabric, seam standards, trims, or inspection batch notes..." <?= $savedQc ? 'readonly' : '' ?>><?= htmlspecialchars($savedQc['defect_notes'] ?? '') ?></textarea>
                        </div>

                        <!-- Program / Batch Summary Card -->
                        <div class="card bg-light border-0 rounded-3 p-3 mt-3" id="batchSummaryCard">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" id="lblBatchNo">
                                    <?= htmlspecialchars($selectedBatch['display_batch_no'] ?? 'NO-BATCH') ?>
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" id="lblTotalStitched">
                                    <?= (int)($selectedBatch['total_pieces'] ?? 0) ?> Stitched Pcs
                                </span>
                            </div>
                            <div class="small fw-bold text-dark mb-0" id="lblProgramName">
                                <?= htmlspecialchars(($selectedBatch['program_no'] ?? 'PRG') . ' — ' . ($selectedBatch['program_name'] ?? 'Production Program')) ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- RIGHT COLUMN: ITEMS READY FOR INSPECTION   -->
            <!-- ========================================== -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-grid-3x3-gap text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark"><?= $savedQc ? 'Saved Inspection Verdicts' : 'Items Ready for Inspection' ?></h6>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="badgeCatalogCount">
                                <?= count(array_unique(array_filter(array_column($batchItems, 'catalog_name')))) ?: 1 ?> Catalog(s)
                            </span>
                            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-outline-secondary btn-sm text-decoration-none text-nowrap">
                                <i class="bi bi-arrow-left me-1"></i> Back to QC List
                            </a>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover align-middle mb-0 qc-create-table-format" id="tableQcItems">
                                <thead>
                                    <tr>
                                        <th>Catalog &amp; Variant</th>
                                        <th class="text-center">Total Qty</th>
                                        <th class="text-center">Passed</th>
                                        <th class="text-center">Rejected</th>
                                        <th class="text-center">Rework</th>
                                        <th class="text-center">Defect / Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyQcItems">
                                    <?php if (empty($batchItems)): ?>
                                        <tr id="rowNoItems"><td colspan="6" class="text-center py-5 text-muted">Select a stitching batch on the left to load variants.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($batchItems as $idx => $it): ?>
                                            <?php 
                                                $q = (int)($it['stitching_qty'] ?: ($it['cut_qty'] ?: 0)); 
                                                $passedQty = (int)($it['qc_passed_qty'] ?? 0);
                                                $rejectedQty = (int)($it['qc_rejected_qty'] ?? 0);
                                                $reworkQty = (int)($it['qc_rework_qty'] ?? 0);
                                            ?>
                                            <tr data-idx="<?= $idx ?>" data-passed="<?= $passedQty ?>" data-rejected="<?= $rejectedQty ?>" data-rework="<?= $reworkQty ?>">
                                                <td class="ps-3 py-2.5">
                                                    <input type="hidden" name="items[<?= $idx ?>][stitching_item_id]" value="<?= (int)$it['id'] ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][catalog_id]" value="<?= (int)($it['catalog_id'] ?? 0) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][variant_id]" value="<?= (int)($it['variant_id'] ?? 0) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][size]" value="<?= htmlspecialchars($it['size'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][color]" value="<?= htmlspecialchars($it['color'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][product_code]" value="<?= htmlspecialchars($it['variant_product_code'] ?? ($it['product_code'] ?? '')) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][variant_name]" value="<?= htmlspecialchars($it['variant_full_name'] ?? ($it['size'] . ' - ' . $it['color'])) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][total_qty]" class="input-total-qty" value="<?= $q ?>">

                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="badge bg-light text-primary border align-self-start font-monospace text-truncate" style="max-width: 260px; font-size:0.75rem;">
                                                            <?= htmlspecialchars($it['catalog_name'] ?: 'Catalog Garment') ?>
                                                        </span>
                                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                            <span class="badge bg-dark text-white px-2 py-0.5" style="font-size:0.72rem;"><?= htmlspecialchars($it['size'] ?: 'Free') ?></span>
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5" style="font-size:0.72rem;"><?= htmlspecialchars($it['color'] ?: 'STD') ?></span>
                                                            <span class="badge bg-light text-secondary border font-monospace px-1.5 py-0.5" style="font-size:0.68rem;">Product Code: <?= htmlspecialchars($it['variant_product_code'] ?: ($it['product_code'] ?: 'N/A')) ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center py-2.5">
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 font-monospace px-2 py-1">
                                                        <?= $q ?>
                                                    </span>
                                                </td>
                                                <td class="text-center py-2.5">
                                                    <?= $savedQc
                                                        ? '<span class="fw-bold text-success">' . $passedQty . '</span>'
                                                        : '<span class="text-muted small">Pending scan</span>' ?>
                                                </td>
                                                <td class="text-center py-2.5">
                                                    <?= $savedQc
                                                        ? '<span class="fw-bold text-danger">' . $rejectedQty . '</span>'
                                                        : '<span class="text-muted small">Pending scan</span>' ?>
                                                </td>
                                                <td class="text-center py-2.5">
                                                    <?= $savedQc
                                                        ? '<span class="fw-bold text-warning-emphasis">' . $reworkQty . '</span>'
                                                        : '<span class="text-muted small">Pending scan</span>' ?>
                                                </td>
                                                <td class="text-center pe-3 py-2.5">
                                                    <span class="small text-muted"><?= $savedQc ? htmlspecialchars($it['qc_defect_notes'] ?? '—') : 'Recorded during scan' ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TOTALS & ACTION BAR -->
                <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6" id="badgeSumPassed">
                                Passed: <?= $qcPassedTotal ?> pcs
                            </span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-2 fs-6" id="badgeSumRejected">
                                Rejected: <?= $qcRejectedTotal ?> pcs
                            </span>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-2 fs-6" id="badgeSumRework">
                                Rework: <?= $qcReworkTotal ?> pcs
                            </span>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-outline-secondary px-3 fw-semibold">
                                Cancel
                            </a>
                            <?php if ($savedQc): ?>
                                <button type="button" class="btn btn-success fw-semibold shadow-sm px-3 py-2 d-inline-flex align-items-center gap-1.5" id="btnAddToStock" onclick="submitQc('add_to_stock')" title="Add passed pieces to stock">
                                    <i class="bi bi-box-arrow-in-down fs-5"></i> Add to Stock
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary fw-bold shadow-sm px-4 py-2 d-inline-flex align-items-center gap-2" id="btnStartQc" onclick="submitQc('save_and_inspect')" title="Initialize QC and open 2D QR Scanner to inspect garment piece-by-piece">
                                    <i class="bi bi-play-circle-fill fs-5"></i> Start QC
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
function onStitchingBatchChange(stitchingId) {
    if (!stitchingId) return;

    fetch('<?= \App\Core\Controller::url('/api/qc/stitching-details') ?>?stitching_id=' + stitchingId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderQcVariants(data.batch, data.items);
            } else {
                alert(data.message || 'Failed to load batch details');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Network error loading stitching batch details');
        });
}

function renderQcVariants(batch, items) {
    document.getElementById('lblBatchNo').textContent = batch.display_batch_no || batch.batch_no || 'STCH';
    const totalStitched = Number(batch.total_pieces || batch.stitched_pieces || (items || []).reduce((sum, item) => {
        return sum + Number(item.stitching_qty || item.cut_qty || 0);
    }, 0));
    document.getElementById('lblTotalStitched').textContent = totalStitched + ' Stitched Pcs';
    document.getElementById('lblProgramName').textContent = (batch.program_no || 'PRG') + ' — ' + (batch.program_name || 'Production Program');

    const tbody = document.getElementById('tbodyQcItems');
    tbody.innerHTML = '';

    if (!items || items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No variant components found for this stitching batch.</td></tr>';
        updateTotals();
        return;
    }

    const catalogs = new Set();

    items.forEach((it, idx) => {
        const q = parseInt(it.stitching_qty || it.cut_qty || 0);
        if (it.catalog_name) catalogs.add(it.catalog_name);

        const tr = document.createElement('tr');
        tr.dataset.idx = idx;
        tr.dataset.passed = '0';
        tr.dataset.rejected = '0';
        tr.dataset.rework = '0';
        tr.innerHTML = `
            <td class="ps-3 py-2.5">
                <input type="hidden" name="items[${idx}][stitching_item_id]" value="${it.id}">
                <input type="hidden" name="items[${idx}][catalog_id]" value="${it.catalog_id || ''}">
                <input type="hidden" name="items[${idx}][variant_id]" value="${it.variant_id || ''}">
                <input type="hidden" name="items[${idx}][size]" value="${escapeHtml(it.size || '')}">
                <input type="hidden" name="items[${idx}][color]" value="${escapeHtml(it.color || '')}">
                <input type="hidden" name="items[${idx}][product_code]" value="${escapeHtml(it.variant_product_code || it.product_code || '')}">
                <input type="hidden" name="items[${idx}][variant_name]" value="${escapeHtml(it.variant_full_name || (it.size + ' - ' + it.color))}">
                <input type="hidden" name="items[${idx}][total_qty]" class="input-total-qty" value="${q}">

                <div class="d-flex flex-column gap-1">
                    <span class="badge bg-light text-primary border align-self-start font-monospace text-truncate" style="max-width: 260px; font-size:0.75rem;">
                        ${escapeHtml(it.catalog_name || 'Catalog Garment')}
                    </span>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <span class="badge bg-dark text-white px-2 py-0.5" style="font-size:0.72rem;">${escapeHtml(it.size || 'Free')}</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5" style="font-size:0.72rem;">${escapeHtml(it.color || 'STD')}</span>
                        <span class="badge bg-light text-secondary border font-monospace px-1.5 py-0.5" style="font-size:0.68rem;">Product Code: ${escapeHtml(it.variant_product_code || it.product_code || 'N/A')}</span>
                    </div>
                </div>
            </td>
            <td class="text-center py-2.5">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 font-monospace px-2 py-1">
                    ${q}
                </span>
            </td>
            <td class="text-center py-2.5">
                <span class="text-muted small">Pending scan</span>
            </td>
            <td class="text-center py-2.5">
                <span class="text-muted small">Pending scan</span>
            </td>
            <td class="text-center py-2.5">
                <span class="text-muted small">Pending scan</span>
            </td>
            <td class="text-center pe-3 py-2.5">
                <span class="small text-muted">Recorded during scan</span>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('badgeCatalogCount').textContent = (catalogs.size || 1) + ' Catalog(s)';
    updateTotals();
}

function updateTotals() {
    let totP = 0, totR = 0, totW = 0;
    document.querySelectorAll('#tbodyQcItems tr').forEach(tr => {
        totP += parseInt(tr.dataset.passed || 0);
        totR += parseInt(tr.dataset.rejected || 0);
        totW += parseInt(tr.dataset.rework || 0);
    });

    document.getElementById('badgeSumPassed').textContent = `Passed: ${totP} pcs`;
    document.getElementById('badgeSumRejected').textContent = `Rejected: ${totR} pcs`;
    document.getElementById('badgeSumRework').textContent = `Rework: ${totW} pcs`;
}

function submitQc(actionType) {
    const form = document.getElementById('formQcCreate');

    if (actionType === 'add_to_stock') {
        const savedQcId = parseInt(document.getElementById('savedQcId')?.value || 0);
        if (!savedQcId) {
            alert('Save the QC inspection before adding pieces to stock.');
            return;
        }
        form.action = '<?= \App\Core\Controller::url('/production/qc/complete-inspection') ?>';
        const btn = document.getElementById('btnAddToStock');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Adding to Stock...';
        }
        form.submit();
        return;
    }

    document.getElementById('submitActionInput').value = 'save_and_inspect';
    // Validation
    const selectBatch = document.getElementById('selectStitchingBatch');
    if (!selectBatch.value) {
        alert('Please select a Source Stitching Batch.');
        selectBatch.focus();
        return;
    }

    const rows = document.querySelectorAll('#tbodyQcItems tr');
    if (rows.length === 0 || document.getElementById('rowNoItems')) {
        alert('No variant items to inspect in this batch.');
        return;
    }

    // Disable button to prevent double submit
    const btnStart = document.getElementById('btnStartQc');
    if (btnStart && actionType === 'save_and_inspect') {
        btnStart.disabled = true;
        btnStart.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Starting QC...';
    }

    form.submit();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

document.addEventListener('DOMContentLoaded', function () {
    updateTotals();

    const quickStaffForm = document.getElementById('formQuickAddQcStaff');
    if (quickStaffForm) {
        quickStaffForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const nameInput = document.getElementById('quickQcStaffName');
            const phoneInput = document.getElementById('quickQcStaffPhone');
            const errBox = document.getElementById('quickQcStaffError');
            const saveBtn = document.getElementById('btnSaveQuickQcStaff');
            const name = nameInput ? nameInput.value.trim() : '';
            const phone = phoneInput ? phoneInput.value.trim() : '';

            if (errBox) {
                errBox.style.display = 'none';
                errBox.textContent = '';
            }
            if (!name) {
                if (errBox) {
                    errBox.textContent = 'Staff name is required.';
                    errBox.style.display = 'block';
                }
                return;
            }
            if (phone && !/^\d{10}$/.test(phone)) {
                if (errBox) {
                    errBox.textContent = 'Phone number must be 10 digits.';
                    errBox.style.display = 'block';
                }
                return;
            }

            const fd = new FormData();
            fd.append('csrf_token', <?= json_encode($csrfToken ?? \App\Core\Auth::csrfToken()) ?>);
            fd.append('name', name);
            fd.append('phone', phone);

            if (saveBtn) saveBtn.disabled = true;
            fetch('<?= \App\Core\Controller::url('/production/qc/add-staff') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success || !data.staff) {
                    if (errBox) {
                        errBox.textContent = (data && data.message) || 'Could not create staff member.';
                        errBox.style.display = 'block';
                    }
                    return;
                }
                const sel = document.getElementById('selectInspector');
                if (sel) {
                    const opt = document.createElement('option');
                    opt.value = data.staff.id;
                    opt.textContent = data.staff.name + (data.staff.role ? ' (' + data.staff.role + ')' : '');
                    opt.selected = true;
                    sel.appendChild(opt);
                }
                quickStaffForm.reset();
                const modalEl = document.getElementById('modalAddQcStaff');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    const inst = bootstrap.Modal.getInstance(modalEl);
                    if (inst) inst.hide();
                }
            })
            .catch(() => {
                if (errBox) {
                    errBox.textContent = 'Network error while saving staff.';
                    errBox.style.display = 'block';
                }
            })
            .finally(() => {
                if (saveBtn) saveBtn.disabled = false;
            });
        });
    }
});
</script>

<?php if (!$savedQc): ?>
<div class="modal fade" id="modalAddQcStaff" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-person-plus text-primary"></i> Add QC Staff
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formQuickAddQcStaff">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Staff Name <span class="text-danger">*</span></label>
                        <input type="text" id="quickQcStaffName" class="form-control" required maxlength="120">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Phone Number (Optional)</label>
                        <input type="tel" id="quickQcStaffPhone" class="form-control" maxlength="10">
                    </div>
                    <div id="quickQcStaffError" class="alert alert-danger py-2 small mb-0" style="display:none;"></div>
                </div>
                <div class="modal-footer border-top bg-light py-2 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btnSaveQuickQcStaff">Save Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
