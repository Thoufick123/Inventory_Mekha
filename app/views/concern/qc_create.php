<?php
// app/views/concern/qc_create.php
// New QC Inspection Batch View (Two-Column ERP Layout matching Image 3)

$currentProg = $selectedProgram ?? ($programs[0] ?? null);
$targetVariantId = (int)($currentProg['target_variant_id'] ?? ($variants[0]['id'] ?? 0));
$plannedQty = (float)($currentProg['planned_qty'] ?? 600);
$remainingToInspect = max(0, $plannedQty - $inspectedCount);
$defaultInspect = $remainingToInspect > 0 ? $remainingToInspect : ($plannedQty > 0 ? $plannedQty : 100);
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

    <!-- Page Title -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-primary"></i> New QC Inspection Batch
            </h4>
            <p class="text-secondary small mb-0">Multi-checkpoint garment inspection & finished goods quality verification</p>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-outline-secondary px-3 py-2 rounded-3 text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Back to QC List
            </a>
        </div>
    </div>

    <form action="<?= \App\Core\Controller::url('/production/qc/store') ?>" method="POST" id="formQcCreate" onsubmit="return validateQcForm(event)">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="concern_id" value="<?= htmlspecialchars((string)($selectedCid ?? \App\Core\Auth::concernId() ?: 1)) ?>">

        <div class="row g-4">
            <!-- Left Column: Inspection Details (Matches Left Column of Image 3) -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <h6 class="mb-0 fw-bold text-dark">Inspection Details</h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Select Production Program / Order -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Select Production Program / Order *</label>
                            <select name="program_id" id="qcProgramSelect" class="form-select border-light-subtle shadow-sm" onchange="onProgramChange(this.value)">
                                <option value="">-- Direct Finished Piece (No Program) --</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?= $prog['id'] ?>" 
                                            data-no="<?= htmlspecialchars($prog['program_no']) ?>"
                                            data-name="<?= htmlspecialchars($prog['program_name']) ?>"
                                            data-variant="<?= (int)($prog['target_variant_id'] ?? 0) ?>"
                                            data-planned="<?= (float)$prog['planned_qty'] ?>"
                                            <?= ($currentProg && $currentProg['id'] == $prog['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prog['program_no']) ?> — <?= htmlspecialchars($prog['program_name']) ?> (<?= (int)$prog['planned_qty'] ?> pcs)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Product Variant Selector -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Product Variant *</label>
                            <select name="variant_id" id="qcVariantSelect" class="form-select border-light-subtle shadow-sm" required>
                                <option value="">-- Select Product Variant --</option>
                                <?php foreach ($variants as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= ($targetVariantId == $v['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['sku_code']) ?>) <?= !empty($v['size']) ? '— ' . htmlspecialchars($v['size']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Program & Product Summary Card (Matches Image 3) -->
                        <div class="p-3 rounded-3 mb-3 border" style="background:#f8fafc;">
                            <span class="text-uppercase text-muted fw-bold" style="font-size:0.7rem; letter-spacing:0.5px;">Program &amp; Product</span>
                            <h6 class="fw-bold mb-3 mt-1 text-dark" id="displayProgramTitle">
                                <?= htmlspecialchars(($currentProg['program_no'] ?? 'PRG-GEN') . ' — ' . ($currentProg['program_name'] ?? 'Direct Finished Garment')) ?>
                            </h6>
                            
                            <div class="row g-2 text-center">
                                <div class="col-3">
                                    <div class="p-2 rounded bg-white border">
                                        <div class="text-muted text-uppercase" style="font-size:0.65rem; font-weight:600;">Planned</div>
                                        <div class="fw-bold text-dark fs-6 mt-1" id="statPlanned"><?= (int)$plannedQty ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="p-2 rounded bg-white border">
                                        <div class="text-muted text-uppercase" style="font-size:0.65rem; font-weight:600;">Inspected</div>
                                        <div class="fw-bold fs-6 mt-1" style="color:#ef4444;" id="statInspected"><?= (int)$inspectedCount ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="p-2 rounded bg-white border">
                                        <div class="text-muted text-uppercase" style="font-size:0.65rem; font-weight:600;">Remaining</div>
                                        <div class="fw-bold fs-6 mt-1" style="color:#10b981;" id="statRemaining"><?= (int)$remainingToInspect ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="p-2 rounded bg-white border">
                                        <div class="text-muted text-uppercase" style="font-size:0.65rem; font-weight:600;">Rework</div>
                                        <div class="fw-bold fs-6 mt-1" style="color:#f59e0b;" id="statRework"><?= (int)$reworkCount ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Destination Warehouse -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Destination Warehouse / Facility *</label>
                            <select name="warehouse_id" class="form-select border-light-subtle shadow-sm">
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Inspection Bay / Location -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Inspection Bay / Location *</label>
                            <input type="text" name="location" class="form-control border-light-subtle shadow-sm" value="Main Store - Inspection Bay 01" required>
                        </div>

                        <!-- Defect Notes / Observations -->
                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-secondary">Defect Notes &amp; Observations</label>
                            <textarea name="defect_notes" id="qcDefectNotes" class="form-control border-light-subtle shadow-sm" rows="3" placeholder="Enter inspection remarks, fabric faults or alteration instructions..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quality Breakdown & Checkpoints (Matches Right Column of Image 3) -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-grid-3x3-gap text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark">Quality Breakdown &amp; Checkpoints</h6>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill px-2 py-1 fw-semibold" style="background:#e0e7ff; color:#4338ca;">
                                <i class="bi bi-patch-check-fill me-1"></i> AQL 1.5 Standard
                            </span>
                            <span class="badge rounded-pill px-3 py-1 fw-bold" id="badgeTotalInspected" style="background:#f1f5f9; color:#0f172a; border: 1px solid #cbd5e1;">
                                <?= (int)$defaultInspect ?> pcs Inspected
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <!-- Inspection Quality Checkpoints Pill Bar -->
                        <div class="mb-4 p-3 rounded-3" style="background:#f8fafc; border:1px solid #e2e8f0;">
                            <div class="small fw-bold text-secondary text-uppercase mb-2" style="font-size:0.7rem; letter-spacing:0.5px;">
                                <i class="bi bi-check2-all text-primary me-1"></i> Mandatory AQL Inspection Checkpoints
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <label class="btn btn-sm btn-outline-secondary bg-white text-dark border d-flex align-items-center gap-1 rounded-pill px-2 py-1 small">
                                    <input type="checkbox" name="checkpoint_stitch" value="1" checked class="form-check-input mt-0 me-1">
                                    <span>Stitching &amp; Seam Integrity</span>
                                </label>
                                <label class="btn btn-sm btn-outline-secondary bg-white text-dark border d-flex align-items-center gap-1 rounded-pill px-2 py-1 small">
                                    <input type="checkbox" name="checkpoint_measurement" value="1" checked class="form-check-input mt-0 me-1">
                                    <span>Measurement Specs (±0.5 cm)</span>
                                </label>
                                <label class="btn btn-sm btn-outline-secondary bg-white text-dark border d-flex align-items-center gap-1 rounded-pill px-2 py-1 small">
                                    <input type="checkbox" name="checkpoint_color" value="1" checked class="form-check-input mt-0 me-1">
                                    <span>Color &amp; Shading Match</span>
                                </label>
                                <label class="btn btn-sm btn-outline-secondary bg-white text-dark border d-flex align-items-center gap-1 rounded-pill px-2 py-1 small">
                                    <input type="checkbox" name="checkpoint_trims" value="1" checked class="form-check-input mt-0 me-1">
                                    <span>Trims, Zippers &amp; Buttons</span>
                                </label>
                                <label class="btn btn-sm btn-outline-secondary bg-white text-dark border d-flex align-items-center gap-1 rounded-pill px-2 py-1 small">
                                    <input type="checkbox" name="checkpoint_labels" value="1" checked class="form-check-input mt-0 me-1">
                                    <span>Barcodes &amp; Care Labels</span>
                                </label>
                            </div>
                        </div>

                        <!-- Quantity Breakdown Inputs (Clean Structured ERP Table) -->
                        <div class="table-responsive mb-4">
                            <table class="table table-borderless align-middle mb-0">
                                <thead style="border-bottom: 2px solid #f1f5f9;">
                                    <tr class="text-uppercase text-secondary" style="font-size:0.75rem;">
                                        <th style="width: 35%;">Category</th>
                                        <th style="width: 35%;">Inspection Status</th>
                                        <th style="width: 30%; text-align:right;">Quantity (Pcs) *</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Inspected Total Row -->
                                    <tr class="border-bottom">
                                        <td>
                                            <div class="fw-bold text-dark">Total Inspected</div>
                                            <div class="text-muted small" style="font-size:0.75rem;">Total garments audited in this batch</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1 rounded">
                                                Audited Batch
                                            </span>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm justify-content-end">
                                                <input type="number" step="1" min="1" name="inspected_qty" id="inputInspected" 
                                                       class="form-control text-end fw-bold fs-6" style="max-width: 140px;" 
                                                       value="<?= (int)$defaultInspect ?>" oninput="recalcQcQuantities('inspected')">
                                                <span class="input-group-text bg-light text-muted">pcs</span>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- QC Passed Row -->
                                    <tr class="border-bottom">
                                        <td>
                                            <div class="fw-bold text-success">QC Passed</div>
                                            <div class="text-muted small" style="font-size:0.75rem;">Approved for batch packing &amp; warehouse</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded">
                                                <i class="bi bi-check-circle me-1"></i> Passed Available
                                            </span>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm justify-content-end">
                                                <input type="number" step="1" min="0" name="passed_qty" id="inputPassed" 
                                                       class="form-control text-end fw-bold fs-6 text-success border-success" style="max-width: 140px; background:#f0fdf4;" 
                                                       value="<?= (int)$defaultInspect ?>" oninput="recalcQcQuantities('passed')">
                                                <span class="input-group-text bg-success bg-opacity-10 text-success">pcs</span>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Rework Required Row -->
                                    <tr class="border-bottom">
                                        <td>
                                            <div class="fw-bold" style="color:#d97706;">Rework / Alterations</div>
                                            <div class="text-muted small" style="font-size:0.75rem;">Minor repairable stitching or trim issues</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1 rounded" style="color:#b45309 !important;">
                                                <i class="bi bi-tools me-1"></i> Minor Defect
                                            </span>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm justify-content-end">
                                                <input type="number" step="1" min="0" name="rework_qty" id="inputRework" 
                                                       class="form-control text-end fw-bold fs-6" style="max-width: 140px; color:#b45309; border-color:#fcd34d; background:#fffbeb;" 
                                                       value="0" oninput="recalcQcQuantities('rework')">
                                                <span class="input-group-text bg-warning bg-opacity-10" style="color:#b45309;">pcs</span>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Rejected / Scrap Row -->
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-danger">Rejected / Scrap</div>
                                            <div class="text-muted small" style="font-size:0.75rem;">Permanent fabric cuts, burn, staining</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded">
                                                <i class="bi bi-x-circle me-1"></i> Non-recoverable
                                            </span>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm justify-content-end">
                                                <input type="number" step="1" min="0" name="rejected_qty" id="inputRejected" 
                                                       class="form-control text-end fw-bold fs-6 text-danger border-danger" style="max-width: 140px; background:#fef2f2;" 
                                                       value="0" oninput="recalcQcQuantities('rejected')">
                                                <span class="input-group-text bg-danger bg-opacity-10 text-danger">pcs</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Defect Tag Helpers (Pill Chips) -->
                        <div>
                            <div class="small fw-semibold text-secondary mb-2" style="font-size:0.75rem;">
                                Quick Defect Tagging (Appends automatically to notes):
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php 
                                $defectChips = ['Broken Stitching', 'Oil/Dirt Spot', 'Measurement Tolerance Off', 'Fabric Shading Variation', 'Faulty Zipper/Trim', 'Uneven Hemming'];
                                foreach ($defectChips as $chip): 
                                ?>
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-2 py-0 text-muted small" 
                                            onclick="addDefectTag('<?= htmlspecialchars($chip) ?>')">
                                        + <?= htmlspecialchars($chip) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Stat Cards Bar (Mirrors Image 3 bottom stats bar) -->
        <div class="card border-0 shadow-sm rounded-3 mt-4">
            <div class="card-body p-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted text-uppercase d-block" style="font-size:0.7rem; font-weight:600;">
                            <i class="bi bi-box me-1"></i> Total Inspected
                        </span>
                        <div class="h5 fw-bold text-dark mb-0 mt-1" id="summaryInspected">
                            <?= (int)$defaultInspect ?> pcs
                        </div>
                    </div>
                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted text-uppercase d-block" style="font-size:0.7rem; font-weight:600;">
                            <i class="bi bi-check-circle-fill text-success me-1"></i> QC Passed
                        </span>
                        <div class="h5 fw-bold mb-0 mt-1" style="color:#10b981;" id="summaryPassed">
                            <?= (int)$defaultInspect ?> pcs
                        </div>
                    </div>
                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted text-uppercase d-block" style="font-size:0.7rem; font-weight:600;">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Rework Required
                        </span>
                        <div class="h5 fw-bold mb-0 mt-1" style="color:#f59e0b;" id="summaryRework">
                            0 pcs
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <span class="text-muted text-uppercase d-block" style="font-size:0.7rem; font-weight:600;">
                            <i class="bi bi-slash-circle-fill text-danger me-1"></i> Defect Rate
                        </span>
                        <div class="h5 fw-bold mb-0 mt-1" style="color:#ef4444;" id="summaryDefectRate">
                            0.0%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions Bar -->
        <div class="d-flex align-items-center justify-content-between mt-4 mb-5">
            <a href="<?= \App\Core\Controller::url('/production/qc') ?>" class="btn btn-light px-4 py-2 border rounded-3 fw-semibold">
                Cancel
            </a>
            <div class="d-flex align-items-center gap-2">
                <button type="submit" name="submit_action" value="save" class="btn btn-outline-primary px-4 py-2 fw-semibold rounded-3 shadow-sm">
                    <i class="bi bi-check-lg me-1"></i> Save QC Inspection
                </button>
                <button type="submit" name="submit_action" value="save_and_pack" class="btn btn-primary px-4 py-2 fw-semibold rounded-3 shadow-sm" style="background:#4f46e5; border-color:#4f46e5;">
                    <i class="bi bi-box-seam me-1"></i> Save &amp; Proceed to Batch Packing
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function onProgramChange(progId) {
    const select = document.getElementById('qcProgramSelect');
    const opt = select.options[select.selectedIndex];
    if (!opt || !progId) {
        document.getElementById('displayProgramTitle').textContent = 'Direct Finished Garment (No Program)';
        document.getElementById('statPlanned').textContent = '—';
        document.getElementById('statInspected').textContent = '0';
        document.getElementById('statRemaining').textContent = '—';
        document.getElementById('statRework').textContent = '0';
        return;
    }

    const progNo = opt.getAttribute('data-no') || '';
    const progName = opt.getAttribute('data-name') || '';
    const planned = parseFloat(opt.getAttribute('data-planned') || 0);
    const variantId = opt.getAttribute('data-variant');

    document.getElementById('displayProgramTitle').textContent = progNo + ' — ' + progName;
    document.getElementById('statPlanned').textContent = Math.round(planned);
    
    // Select the variant automatically if matched
    if (variantId) {
        const vSelect = document.getElementById('qcVariantSelect');
        if (vSelect) {
            vSelect.value = variantId;
        }
    }

    // Reload page with query param for live stats recalculation if desired
    window.location.href = "<?= \App\Core\Controller::url('/production/qc/create') ?>?program_id=" + progId;
}

function recalcQcQuantities(source) {
    let inspected = parseFloat(document.getElementById('inputInspected').value) || 0;
    let passed = parseFloat(document.getElementById('inputPassed').value) || 0;
    let rework = parseFloat(document.getElementById('inputRework').value) || 0;
    let rejected = parseFloat(document.getElementById('inputRejected').value) || 0;

    if (source === 'inspected') {
        passed = Math.max(0, inspected - rework - rejected);
        document.getElementById('inputPassed').value = passed;
    } else if (source === 'rework' || source === 'rejected') {
        passed = Math.max(0, inspected - rework - rejected);
        document.getElementById('inputPassed').value = passed;
    } else if (source === 'passed') {
        inspected = passed + rework + rejected;
        document.getElementById('inputInspected').value = inspected;
    }

    // Update Live Badges & Footer
    document.getElementById('badgeTotalInspected').textContent = Math.round(inspected) + ' pcs Inspected';
    document.getElementById('summaryInspected').textContent = Math.round(inspected) + ' pcs';
    document.getElementById('summaryPassed').textContent = Math.round(passed) + ' pcs';
    document.getElementById('summaryRework').textContent = Math.round(rework) + ' pcs';

    const defectCount = rework + rejected;
    const defectRate = inspected > 0 ? ((defectCount / inspected) * 100).toFixed(1) : '0.0';
    document.getElementById('summaryDefectRate').textContent = defectRate + '% (' + Math.round(defectCount) + ' pcs)';
}

function addDefectTag(tag) {
    const notes = document.getElementById('qcDefectNotes');
    if (!notes) return;
    if (notes.value.trim() === '') {
        notes.value = '[' + tag + ']';
    } else if (!notes.value.includes('[' + tag + ']')) {
        notes.value = notes.value.trim() + ', [' + tag + ']';
    }
    notes.focus();
}

function validateQcForm(e) {
    const variantId = document.getElementById('qcVariantSelect').value;
    const inspected = parseFloat(document.getElementById('inputInspected').value) || 0;
    const passed = parseFloat(document.getElementById('inputPassed').value) || 0;
    const rework = parseFloat(document.getElementById('inputRework').value) || 0;
    const rejected = parseFloat(document.getElementById('inputRejected').value) || 0;

    if (!variantId) {
        alert('Please select a Product Variant.');
        e.preventDefault();
        return false;
    }

    if (inspected <= 0) {
        alert('Inspected quantity must be greater than zero.');
        e.preventDefault();
        return false;
    }

    if (Math.abs(inspected - (passed + rework + rejected)) > 0.01) {
        alert('Sum of Passed (' + passed + ') + Rework (' + rework + ') + Rejected (' + rejected + ') must equal Inspected (' + inspected + ').');
        e.preventDefault();
        return false;
    }

    return true;
}
</script>
