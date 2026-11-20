<?php
// app/views/concern/qc_create.php
// Initialize QC Inspection Batch (Matches Screenshot 3)
?>
<div class="container-fluid px-3 py-2">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small text-muted mb-0">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="text-decoration-none text-muted">QC Inspections</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">New QC Inspection Batch</li>
        </ol>
    </nav>

    <!-- Page Title Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center justify-content-center text-danger" style="width: 28px; height: 28px; border: 2px solid #ef4444; border-radius: 50%; font-size: 0.95rem;">
                    <i class="bi bi-patch-check"></i>
                </span>
                Initialize QC Inspection Batch
            </h4>
            <p class="text-secondary small mb-0">Select a completed stitching batch to begin quality control inspection</p>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-outline-secondary px-3 py-2 rounded-3 text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Back to QC List
            </a>
        </div>
    </div>

    <form action="<?= \App\Core\Controller::url('/production/qc/store') ?>" method="POST" id="formQcCreate" onsubmit="return validateQcForm(event)">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="concern_id" value="<?= htmlspecialchars((string)$selectedCid) ?>">
        <input type="hidden" name="submit_action" id="submitActionInput" value="direct_inward">

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
                            <select name="stitching_batch_id" id="selectStitchingBatch" class="form-select border-light-subtle shadow-sm" required onchange="onStitchingBatchChange(this.value)">
                                <option value="">-- Choose Stitching Batch --</option>
                                <?php foreach ($stitchingBatches as $sb): ?>
                                    <option value="<?= $sb['id'] ?>"
                                            data-no="<?= htmlspecialchars($sb['display_batch_no']) ?>"
                                            data-program="<?= htmlspecialchars(($sb['program_no'] ?? 'PRG') . ' — ' . ($sb['program_name'] ?? 'Production Program')) ?>"
                                            data-pieces="<?= (int)$sb['total_pieces'] ?>"
                                            data-variants="<?= (int)$sb['variant_count'] ?>"
                                            <?= ($selectedStitchingId == $sb['id']) ? 'selected' : '' ?>>
                                        Batch #<?= $sb['id'] ?> (<?= htmlspecialchars($sb['display_batch_no']) ?> • <?= htmlspecialchars($sb['program_name'] ?: 'Garments') ?> - <?= (int)$sb['total_pieces'] ?> pcs)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small text-muted">Choose a stitched batch to dynamically load all cut & stitched variants.</div>
                        </div>

                        <!-- QC Inspector -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold text-secondary mb-0">
                                    <i class="bi bi-person-badge text-primary me-1"></i> QC Inspector
                                </label>
                                <span class="badge bg-light text-primary border px-2 py-0.5" style="font-size:0.75rem;">Verified Staff</span>
                            </div>
                            <select name="inspector_id" id="selectInspector" class="form-select border-light-subtle shadow-sm" required>
                                <?php foreach ($inspectors as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($u['id'] == \App\Core\Auth::id()) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['name'] . ($u['role'] ? ' (' . ucfirst($u['role']) . ')' : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Destination Warehouse -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Destination Warehouse / Location *</label>
                            <select name="location" class="form-select border-light-subtle shadow-sm">
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= htmlspecialchars($wh['name']) ?>" <?= $wh['id'] == 1 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wh['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- General Inspection Notes -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">General Inspection Notes</label>
                            <textarea name="notes" class="form-control border-light-subtle shadow-sm" rows="3" 
                                      placeholder="Fabric, seam standards, trims, or inspection batch notes..."></textarea>
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
                            <div class="small fw-bold text-dark mb-1" id="lblProgramName">
                                <?= htmlspecialchars(($selectedBatch['program_no'] ?? 'PRG') . ' — ' . ($selectedBatch['program_name'] ?? 'Production Program')) ?>
                            </div>
                            <div class="text-muted small">
                                All passed pieces will be credited directly to Live Stock upon approval.
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
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-grid-3x3-gap text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark">Items Ready for Inspection</h6>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="badgeCatalogCount">
                            <?= count(array_unique(array_filter(array_column($batchItems, 'catalog_name')))) ?: 1 ?> Catalog(s)
                        </span>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tableQcItems">
                                <thead class="table-light text-uppercase small text-secondary" style="font-size:0.75rem;">
                                    <tr>
                                        <th class="ps-3 py-2.5">Catalog & Variant Attribute</th>
                                        <th class="text-center py-2.5" style="width: 100px;">Total Qty</th>
                                        <th class="text-center py-2.5" style="width: 100px;">Passed (Pcs)</th>
                                        <th class="text-center py-2.5" style="width: 100px;">Rejected</th>
                                        <th class="text-center py-2.5" style="width: 100px;">Rework</th>
                                        <th class="text-center pe-3 py-2.5" style="width: 130px;">Defect / Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyQcItems">
                                    <?php if (empty($batchItems)): ?>
                                        <tr id="rowNoItems"><td colspan="6" class="text-center py-5 text-muted">Select a stitching batch on the left to load variants.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($batchItems as $idx => $it): ?>
                                            <?php 
                                                $q = (int)($it['stitching_qty'] ?: ($it['cut_qty'] ?: 0)); 
                                            ?>
                                            <tr data-idx="<?= $idx ?>">
                                                <td class="ps-3 py-2.5">
                                                    <input type="hidden" name="items[<?= $idx ?>][stitching_item_id]" value="<?= (int)$it['id'] ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][catalog_id]" value="<?= (int)($it['catalog_id'] ?? 0) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][variant_id]" value="<?= (int)($it['variant_id'] ?? 0) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][size]" value="<?= htmlspecialchars($it['size'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][color]" value="<?= htmlspecialchars($it['color'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][sku_code]" value="<?= htmlspecialchars($it['variant_sku'] ?? ($it['sku_code'] ?? '')) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][variant_name]" value="<?= htmlspecialchars($it['variant_full_name'] ?? ($it['size'] . ' - ' . $it['color'])) ?>">
                                                    <input type="hidden" name="items[<?= $idx ?>][total_qty]" class="input-total-qty" value="<?= $q ?>">

                                                    <div class="d-flex flex-column gap-1">
                                                        <span class="badge bg-light text-primary border align-self-start font-monospace text-truncate" style="max-width: 260px; font-size:0.75rem;">
                                                            <?= htmlspecialchars($it['catalog_name'] ?: 'Catalog Garment') ?>
                                                        </span>
                                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                            <span class="badge bg-dark text-white px-2 py-0.5" style="font-size:0.72rem;"><?= htmlspecialchars($it['size'] ?: 'Free') ?></span>
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5" style="font-size:0.72rem;"><?= htmlspecialchars($it['color'] ?: 'STD') ?></span>
                                                            <span class="badge bg-light text-secondary border font-monospace px-1.5 py-0.5" style="font-size:0.68rem;">SKU: <?= htmlspecialchars($it['variant_sku'] ?: ($it['sku_code'] ?: 'N/A')) ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center py-2.5">
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 font-monospace px-2 py-1">
                                                        <?= $q ?>
                                                    </span>
                                                </td>
                                                <td class="text-center py-2.5">
                                                    <input type="number" name="items[<?= $idx ?>][passed_qty]" 
                                                           class="form-control form-control-sm text-center fw-bold text-success border-success-subtle input-passed" 
                                                           value="<?= $q ?>" min="0" max="<?= $q ?>" required oninput="recalcRow(this)">
                                                </td>
                                                <td class="text-center py-2.5">
                                                    <input type="number" name="items[<?= $idx ?>][rejected_qty]" 
                                                           class="form-control form-control-sm text-center fw-bold text-danger border-danger-subtle input-rejected" 
                                                           value="0" min="0" max="<?= $q ?>" oninput="recalcRow(this)">
                                                </td>
                                                <td class="text-center py-2.5">
                                                    <input type="number" name="items[<?= $idx ?>][rework_qty]" 
                                                           class="form-control form-control-sm text-center fw-bold text-warning-emphasis border-warning-subtle input-rework" 
                                                           value="0" min="0" max="<?= $q ?>" oninput="recalcRow(this)">
                                                </td>
                                                <td class="text-center pe-3 py-2.5">
                                                    <input type="text" name="items[<?= $idx ?>][defect_notes]" 
                                                           class="form-control form-control-sm" placeholder="Defect/rework details...">
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
                                Passed: <?= array_sum(array_column($batchItems, 'stitching_qty')) ?: 0 ?> pcs
                            </span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-2 fs-6" id="badgeSumRejected">
                                Rejected: 0 pcs
                            </span>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-2 fs-6" id="badgeSumRework">
                                Rework: 0 pcs
                            </span>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-outline-secondary px-3 fw-semibold">
                                Cancel
                            </a>
                            <button type="button" class="btn btn-outline-primary fw-semibold shadow-sm px-3" onclick="submitQc('save_and_inspect')" title="Open 2D QR Scanner to inspect piece by piece">
                                <i class="bi bi-qr-code-scan me-1"></i> Start 2D QR Scanner
                            </button>
                            <button type="button" class="btn btn-success fw-semibold shadow-sm px-4" onclick="submitQc('direct_inward')">
                                <i class="bi bi-check2-circle me-1"></i> Pass & Inward to Stock
                            </button>
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
    document.getElementById('lblTotalStitched').textContent = (batch.stitched_pieces || 0) + ' Stitched Pcs';
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
        tr.innerHTML = `
            <td class="ps-3 py-2.5">
                <input type="hidden" name="items[${idx}][stitching_item_id]" value="${it.id}">
                <input type="hidden" name="items[${idx}][catalog_id]" value="${it.catalog_id || ''}">
                <input type="hidden" name="items[${idx}][variant_id]" value="${it.variant_id || ''}">
                <input type="hidden" name="items[${idx}][size]" value="${escapeHtml(it.size || '')}">
                <input type="hidden" name="items[${idx}][color]" value="${escapeHtml(it.color || '')}">
                <input type="hidden" name="items[${idx}][sku_code]" value="${escapeHtml(it.variant_sku || it.sku_code || '')}">
                <input type="hidden" name="items[${idx}][variant_name]" value="${escapeHtml(it.variant_full_name || (it.size + ' - ' + it.color))}">
                <input type="hidden" name="items[${idx}][total_qty]" class="input-total-qty" value="${q}">

                <div class="d-flex flex-column gap-1">
                    <span class="badge bg-light text-primary border align-self-start font-monospace text-truncate" style="max-width: 260px; font-size:0.75rem;">
                        ${escapeHtml(it.catalog_name || 'Catalog Garment')}
                    </span>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <span class="badge bg-dark text-white px-2 py-0.5" style="font-size:0.72rem;">${escapeHtml(it.size || 'Free')}</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5" style="font-size:0.72rem;">${escapeHtml(it.color || 'STD')}</span>
                        <span class="badge bg-light text-secondary border font-monospace px-1.5 py-0.5" style="font-size:0.68rem;">SKU: ${escapeHtml(it.variant_sku || it.sku_code || 'N/A')}</span>
                    </div>
                </div>
            </td>
            <td class="text-center py-2.5">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 font-monospace px-2 py-1">
                    ${q}
                </span>
            </td>
            <td class="text-center py-2.5">
                <input type="number" name="items[${idx}][passed_qty]" 
                       class="form-control form-control-sm text-center fw-bold text-success border-success-subtle input-passed" 
                       value="${q}" min="0" max="${q}" required oninput="recalcRow(this)">
            </td>
            <td class="text-center py-2.5">
                <input type="number" name="items[${idx}][rejected_qty]" 
                       class="form-control form-control-sm text-center fw-bold text-danger border-danger-subtle input-rejected" 
                       value="0" min="0" max="${q}" oninput="recalcRow(this)">
            </td>
            <td class="text-center py-2.5">
                <input type="number" name="items[${idx}][rework_qty]" 
                       class="form-control form-control-sm text-center fw-bold text-warning-emphasis border-warning-subtle input-rework" 
                       value="0" min="0" max="${q}" oninput="recalcRow(this)">
            </td>
            <td class="text-center pe-3 py-2.5">
                <input type="text" name="items[${idx}][defect_notes]" 
                       class="form-control form-control-sm" placeholder="Defect/rework details...">
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('badgeCatalogCount').textContent = (catalogs.size || 1) + ' Catalog(s)';
    updateTotals();
}

function recalcRow(inputElem) {
    const tr = inputElem.closest('tr');
    const tot = parseInt(tr.querySelector('.input-total-qty').value) || 0;
    const passedInput = tr.querySelector('.input-passed');
    const rejInput = tr.querySelector('.input-rejected');
    const rewInput = tr.querySelector('.input-rework');

    let rej = parseInt(rejInput.value) || 0;
    let rew = parseInt(rewInput.value) || 0;

    if (inputElem === rejInput || inputElem === rewInput) {
        // Auto adjust passed if rej or rew changed
        const currentPassed = Math.max(0, tot - (rej + rew));
        passedInput.value = currentPassed;
    }

    updateTotals();
}

function updateTotals() {
    let totP = 0, totR = 0, totW = 0;
    document.querySelectorAll('#tbodyQcItems tr').forEach(tr => {
        totP += parseInt(tr.querySelector('.input-passed')?.value || 0);
        totR += parseInt(tr.querySelector('.input-rejected')?.value || 0);
        totW += parseInt(tr.querySelector('.input-rework')?.value || 0);
    });

    document.getElementById('badgeSumPassed').textContent = `Passed: ${totP} pcs`;
    document.getElementById('badgeSumRejected').textContent = `Rejected: ${totR} pcs`;
    document.getElementById('badgeSumRework').textContent = `Rework: ${totW} pcs`;
}

function submitQc(actionType) {
    document.getElementById('submitActionInput').value = actionType;
    const form = document.getElementById('formQcCreate');

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

    let hasError = false;
    rows.forEach(tr => {
        const tot = parseInt(tr.querySelector('.input-total-qty')?.value || 0);
        const p = parseInt(tr.querySelector('.input-passed')?.value || 0);
        const r = parseInt(tr.querySelector('.input-rejected')?.value || 0);
        const w = parseInt(tr.querySelector('.input-rework')?.value || 0);
        if (p + r + w > tot) {
            alert(`Row sum (${p + r + w}) exceeds total stitched pieces (${tot}). Please correct.`);
            hasError = true;
        }
    });

    if (hasError) return;

    form.submit();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

document.addEventListener('DOMContentLoaded', function () {
    updateTotals();
});
</script>
