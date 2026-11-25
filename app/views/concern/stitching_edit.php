<?php
/**
 * View: Edit Stitching Batch
 * Location: inventory_mekha/app/views/concern/stitching_edit.php
 * Matches Image 3 (Populated batch, Dynamic Multi-Catalog breakdown, Live Weights & Prices, Weight Matched Badge)
 */
?>
<div class="container-fluid px-4 py-3">

    <!-- BREADCRUMBS -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/stitching') ?>" class="text-decoration-none text-muted">Stitching</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Edit: <?= htmlspecialchars($batch['display_batch_no']) ?></li>
        </ol>
    </nav>

    <!-- HEADER & ACTIONS BAR -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-stack text-primary"></i> Edit Stitching Batch
                <span class="badge bg-light text-primary font-monospace border fs-6"><?= htmlspecialchars($batch['display_batch_no']) ?></span>
            </h4>
            <p class="text-muted small mb-0">
                Edit variant-wise quantities, per-piece weights, base & discount prices for Stitching Batch <strong class="text-dark font-monospace"><?= htmlspecialchars($batch['display_batch_no']) ?></strong>.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= \App\Core\Controller::url('/production/stitching') ?>" class="btn btn-light border shadow-sm fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <form action="<?= \App\Core\Controller::url('/production/stitching/update') ?>" method="POST" id="formEditStitching" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= (int)$batch['id'] ?>">

        <div class="row g-4">
            <!-- ========================================== -->
            <!-- LEFT COLUMN: 1. STITCHING BATCH DETAILS    -->
            <!-- ========================================== -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-primary"></i>
                            1. Stitching Batch Details
                        </h6>
                    </div>
                    <div class="card-body p-4">

                        <!-- Stitching Name * -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark mb-1">
                                Stitching Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="stitching_name" id="stitchingName" 
                                   class="form-control text-uppercase fw-semibold" 
                                   value="<?= htmlspecialchars(strtoupper($batch['stitching_name'] ?? '')) ?>"
                                   oninput="this.value = this.value.toUpperCase();" required>
                            <div class="invalid-feedback">Please enter a Stitching Name.</div>
                        </div>

                        <!-- Batch Number -->
                        <div class="mb-3">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($batch['status'] ?? 'in_progress') ?>">
                            <label class="form-label fw-semibold text-dark mb-1">Batch Number</label>
                            <input type="text" class="form-control bg-light fw-bold text-secondary font-monospace" 
                                   value="<?= htmlspecialchars($batch['display_batch_no']) ?>" readonly>
                        </div>

                        <!-- Source Cutting Batch Card -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark mb-1">
                                <i class="bi bi-scissors text-danger me-1"></i> Source Cutting Batch
                            </label>
                            <input type="text" class="form-control bg-light text-dark fw-semibold" 
                                   value="<?= htmlspecialchars(($batch['cutting_name'] ? $batch['cutting_name'] . ' (' . ($batch['cutting_batch_no'] ?: 'CUT') . ')' : ($batch['cutting_batch_no'] ?: 'Cutting Batch'))) ?>" readonly>

                            <div class="card bg-light border-0 rounded-3 p-3 mt-2">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="fw-bold text-dark font-monospace small">
                                        [<?= htmlspecialchars($batch['program_no'] ?: 'PRG') ?>] [<?= htmlspecialchars($batch['cutting_batch_no'] ?: 'CUT') ?>]
                                    </div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <?= number_format((float)$batch['cut_weight'], 2) ?> kg Usable
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    Assigned Catalog: 
                                    <?php 
                                        $catNames = array_unique(array_filter(array_column($items, 'catalog_name')));
                                        echo htmlspecialchars(!empty($catNames) ? implode(', ', $catNames) : 'Production Garments');
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Tailor / Stitching Master -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold text-dark mb-0">
                                    Tailor / Stitching Master <span class="text-danger">*</span>
                                </label>
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none fw-semibold" 
                                        data-bs-toggle="modal" data-bs-target="#modalAddTailor">
                                    <i class="bi bi-plus"></i> Add Tailor
                                </button>
                            </div>
                            <select name="tailor_id" id="selectTailor" class="form-select" required>
                                <option value="">Select Tailor / Stitching Master</option>
                                <?php foreach ($tailors as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= ($batch['tailor_id'] == $t['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['name'] . ($t['phone'] ? ' (' . $t['phone'] . ')' : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a Tailor / Stitching Master.</div>
                        </div>

                        <!-- Notes & Remarks -->
                        <div class="mb-2">
                            <label class="form-label fw-semibold text-dark mb-1">Notes & Remarks</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Stitching instructions, lot details, etc..."><?= htmlspecialchars($batch['notes'] ?? '') ?></textarea>
                        </div>

                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- RIGHT COLUMN: 2. STITCHING QUANTITIES, WEIGHTS & PRICING         -->
            <!-- =============================================================== -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                    <i class="bi bi-grid-3x3-gap text-primary"></i>
                                    2. Stitching Quantities, Weights & Pricing
                                </h6>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2 justify-content-md-end">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1.5 px-2.5" id="badgeCutPcs">
                                    <?= (int)$batch['cut_pieces'] ?> Cut pcs
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1.5 px-2.5" id="badgeStitchedPcs">
                                    <?= (int)$batch['stitched_pieces'] ?> Stitched pcs
                                </span>
                                <span class="badge bg-light text-secondary border py-1.5 px-2.5" id="badgeCutWt">
                                    Cut Wt: <?= number_format((float)$batch['cut_weight'], 2) ?> kg
                                </span>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-1.5 px-2.5" id="badgeStitchWt">
                                    Stitch Wt: <?= number_format((float)$batch['stitch_weight'], 2) ?> kg
                                </span>
                                <span class="badge bg-success text-white py-1.5 px-2.5" id="badgeWeightMatched">
                                    <i class="bi bi-check2-circle me-1"></i>Weight Matched
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BULK CATALOG PRICING TOOLBAR -->
                    <div id="pricingToolbar" class="bg-light px-3 py-2.5 border-bottom">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="small fw-bold text-secondary d-flex align-items-center gap-1 text-nowrap">
                                <i class="bi bi-tags-fill text-primary"></i> Catalog Pricing:
                            </span>
                            <div class="flex-grow-1" style="min-width: 180px; max-width: 280px;">
                                <select id="bulkCatalogSelect" class="form-select form-select-sm">
                                    <option value="">-- All Catalogs in Batch --</option>
                                    <?php foreach ($catalogs as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name'] . ' (' . $cat['code'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="width: 110px;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" id="bulkBasePrice" class="form-control" placeholder="Base ₹">
                                </div>
                            </div>
                            <div style="width: 110px;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" id="bulkDiscPrice" class="form-control" placeholder="Disc ₹">
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-dark fw-semibold text-nowrap px-3" id="btnApplyBulkPricing">
                                Set Prices to Table
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tableStitchingItems">
                                <thead class="table-light text-uppercase small text-secondary">
                                    <tr>
                                        <th class="ps-3 py-2.5">Catalog & Variant Attribute</th>
                                        <th class="text-center py-2.5" style="width: 100px;">Cut Qty</th>
                                        <th class="text-center py-2.5" style="width: 120px;">Stitching Qty (Pcs) <span class="text-danger">*</span></th>
                                        <th class="text-center py-2.5" style="width: 110px;">Per Pc Wt (kg) <span class="text-danger">*</span></th>
                                        <th class="text-center py-2.5" style="width: 110px;">Total Wt (kg) <span class="text-danger">*</span></th>
                                        <th class="text-center py-2.5" style="width: 125px;">Base Price (₹) <span class="text-danger">*</span></th>
                                        <th class="text-center pe-3 py-2.5" style="width: 125px;">Discount Price (₹) <span class="text-danger">*</span></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyStitchingItems">
                                    <?php foreach ($items as $it): ?>
                                        <tr data-catalog-id="<?= (int)$it['catalog_id'] ?>" data-item-id="<?= (int)$it['id'] ?>">
                                            <td class="ps-3 py-2">
                                                <input type="hidden" name="items[<?= (int)$it['id'] ?>][cut_qty]" class="input-cut-qty" value="<?= (int)$it['cut_qty'] ?>">

                                                <div class="d-flex flex-column">
                                                    <span class="badge bg-light text-dark border align-self-start mb-1 text-truncate" style="max-width: 250px;">
                                                        <i class="bi bi-tag text-primary me-1"></i> <?= htmlspecialchars($it['catalog_name'] ?: 'Catalog Item') ?>
                                                    </span>
                                                    <div class="fw-bold text-dark small">
                                                        <?= htmlspecialchars($it['variant_name'] ?: ($it['size'] . ' - ' . $it['color'])) ?>
                                                    </div>
                                                    <div class="text-muted font-monospace" style="font-size: 0.75rem;">
                                                        Product Code: <?= htmlspecialchars($it['product_code'] ?: 'N/A') ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center py-2">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-6 font-monospace">
                                                    <?= (int)$it['cut_qty'] ?> pcs
                                                </span>
                                            </td>
                                            <td class="text-center py-2">
                                                <input type="number" name="items[<?= (int)$it['id'] ?>][stitching_qty]" 
                                                       class="form-control form-control-sm text-center fw-bold input-stitch-qty" 
                                                       value="<?= (int)$it['stitching_qty'] ?>" min="0" required placeholder="0">
                                                <div class="invalid-feedback">Required</div>
                                            </td>
                                            <td class="text-center py-2">
                                                <input type="number" step="0.001" name="items[<?= (int)$it['id'] ?>][per_pc_weight]" 
                                                       class="form-control form-control-sm text-center font-monospace input-per-pc-wt" 
                                                       value="<?= number_format((float)$it['per_pc_weight'], 3, '.', '') ?>" min="0.001" required placeholder="0.000">
                                                <div class="invalid-feedback">Required</div>
                                            </td>
                                            <td class="text-center py-2">
                                                <input type="number" step="0.01" name="items[<?= (int)$it['id'] ?>][total_weight]" 
                                                       class="form-control form-control-sm text-center font-monospace bg-light fw-bold input-row-total-wt" 
                                                       value="<?= number_format((float)$it['total_weight'], 2, '.', '') ?>" min="0.01" required placeholder="0.00">
                                                <div class="invalid-feedback">Required</div>
                                            </td>
                                            <td class="text-center py-2">
                                                <div class="input-group input-group-sm has-validation">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" step="0.01" name="items[<?= (int)$it['id'] ?>][base_price]" 
                                                           class="form-control form-control-sm text-end input-base-price" 
                                                           value="<?= number_format((float)$it['base_price'], 2, '.', '') ?>" min="0" required placeholder="0.00">
                                                    <div class="invalid-feedback">Required</div>
                                                </div>
                                            </td>
                                            <td class="text-center pe-3 py-2">
                                                <div class="input-group input-group-sm has-validation">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" step="0.01" name="items[<?= (int)$it['id'] ?>][discount_price]" 
                                                           class="form-control form-control-sm text-end input-disc-price" 
                                                           value="<?= number_format((float)$it['discount_price'], 2, '.', '') ?>" min="0" required placeholder="0.00">
                                                    <div class="invalid-feedback">Required</div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- VALIDATION ERROR ALERT -->
                <div id="stitchingValidationError" class="alert alert-danger align-items-center gap-2 mb-3 py-2 px-3 shadow-sm rounded-3" style="display: none;">
                    <i class="bi bi-exclamation-triangle-fill fs-5 text-danger flex-shrink-0"></i>
                    <div class="fw-semibold small" id="stitchingValidationMessage">Please fill in all empty required fields before saving.</div>
                </div>

                <!-- BOTTOM ACTIONS BAR -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <a href="<?= \App\Core\Controller::url('/production/stitching') ?>" class="btn btn-outline-secondary px-4 fw-semibold">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnUpdateStitching">
                        <i class="bi bi-check2-circle me-1"></i> Update Stitching Batch
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>

<!-- ============================================================== -->
<!-- MODAL: QUICK ADD TAILOR / STITCHING MASTER                    -->
<!-- ============================================================== -->
<div class="modal fade" id="modalAddTailor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-person-badge text-primary"></i> Add Tailor / Stitching Master
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formQuickAddTailor">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Tailor Name <span class="text-danger">*</span></label>
                        <input type="text" id="quickTailorName" class="form-control" placeholder="e.g. Master Ramesh, Suresh Tailor" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Phone Number</label>
                        <input type="tel" id="quickTailorPhone" class="form-control" placeholder="10-digit mobile number" maxlength="10">
                        <div id="quickTailorPhoneError" class="text-danger small mt-1 d-none" style="font-size: 0.75rem;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Employee / Master Code</label>
                        <input type="text" id="quickTailorCode" class="form-control" placeholder="e.g. TL-004">
                    </div>
                    <div id="quickTailorError" class="alert alert-danger py-2 small mb-0" style="display:none;"></div>
                </div>
                <div class="modal-footer border-top bg-light py-2 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btnSaveQuickTailor">Save Tailor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('tbodyStitchingItems');
    const badgeCutPcs = document.getElementById('badgeCutPcs');
    const badgeStitchedPcs = document.getElementById('badgeStitchedPcs');
    const badgeCutWt = document.getElementById('badgeCutWt');
    const badgeStitchWt = document.getElementById('badgeStitchWt');
    const badgeWeightMatched = document.getElementById('badgeWeightMatched');

    const btnCopyCutQty = document.getElementById('btnCopyCutQty');
    const btnApplyBulkPricing = document.getElementById('btnApplyBulkPricing');
    const bulkCatalogSelect = document.getElementById('bulkCatalogSelect');

    const cutWeight = <?= (float)$batch['cut_weight'] ?>;

    function attachRowEvents() {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(tr => {
            const stitchQtyInput = tr.querySelector('.input-stitch-qty');
            const perPcWtInput = tr.querySelector('.input-per-pc-wt');
            const rowTotalWtInput = tr.querySelector('.input-row-total-wt');

            function updateRowWeight() {
                const qty = parseInt(stitchQtyInput.value) || 0;
                const perWt = parseFloat(perPcWtInput.value) || 0;
                const totWt = (qty * perWt).toFixed(2);
                rowTotalWtInput.value = totWt;
                recalculateTotals();
            }

            stitchQtyInput.addEventListener('input', updateRowWeight);
            perPcWtInput.addEventListener('input', updateRowWeight);
            rowTotalWtInput.addEventListener('input', recalculateTotals);
        });
    }

    // Copy Cut Qty button
    btnCopyCutQty?.addEventListener('click', function () {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(tr => {
            const cutVal = tr.querySelector('.input-cut-qty').value;
            const stitchInput = tr.querySelector('.input-stitch-qty');
            stitchInput.value = cutVal;
            stitchInput.dispatchEvent(new Event('input'));
        });
    });

    // Apply Bulk Pricing
    btnApplyBulkPricing.addEventListener('click', function () {
        const targetCatalogId = bulkCatalogSelect.value;
        const baseVal = parseFloat(document.getElementById('bulkBasePrice').value);
        const discVal = parseFloat(document.getElementById('bulkDiscPrice').value);

        if (isNaN(baseVal) && isNaN(discVal)) {
            alert('Please enter a Base Price or Discount Price.');
            return;
        }

        const rows = tbody.querySelectorAll('tr');
        let matchedCount = 0;
        rows.forEach(tr => {
            if (!targetCatalogId || tr.dataset.catalogId === targetCatalogId) {
                if (!isNaN(baseVal)) {
                    tr.querySelector('.input-base-price').value = baseVal.toFixed(2);
                }
                if (!isNaN(discVal)) {
                    tr.querySelector('.input-disc-price').value = discVal.toFixed(2);
                }
                matchedCount++;
            }
        });

        alert(`Updated prices for ${matchedCount} variant row(s).`);
    });

    function recalculateTotals() {
        let totalCut = 0;
        let totalStitched = 0;
        let totalStitchWeight = 0;

        const rows = tbody.querySelectorAll('tr');
        rows.forEach(tr => {
            const cutQ = parseInt(tr.querySelector('.input-cut-qty')?.value) || 0;
            const stitchQ = parseInt(tr.querySelector('.input-stitch-qty')?.value) || 0;
            const totWt = parseFloat(tr.querySelector('.input-row-total-wt')?.value) || 0;

            totalCut += cutQ;
            totalStitched += stitchQ;
            totalStitchWeight += totWt;
        });

        badgeCutPcs.textContent = `${totalCut} Cut pcs`;
        badgeStitchedPcs.textContent = `${totalStitched} Stitched pcs`;
        badgeCutWt.textContent = `Cut Wt: ${cutWeight.toFixed(2)} kg`;
        badgeStitchWt.textContent = `Stitch Wt: ${totalStitchWeight.toFixed(2)} kg`;

        // Check if weight matches cut weight (within 0.05 kg tolerance)
        const diff = Math.abs(totalStitchWeight - cutWeight);
        if (cutWeight > 0 && diff <= 0.05) {
            badgeWeightMatched.style.display = 'inline-block';
            badgeWeightMatched.className = 'badge bg-success text-white py-1.5 px-2';
            badgeWeightMatched.innerHTML = '<i class="bi bi-check2-circle"></i> Weight Matched';
        } else if (totalStitchWeight > cutWeight) {
            badgeWeightMatched.style.display = 'inline-block';
            badgeWeightMatched.className = 'badge bg-danger text-white py-1.5 px-2';
            badgeWeightMatched.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Exceeds Cut Wt by ${(totalStitchWeight - cutWeight).toFixed(2)} kg`;
        } else {
            badgeWeightMatched.style.display = 'none';
        }
    }

    attachRowEvents();
    recalculateTotals();

    // ==========================================
    // CLIENT-SIDE FORM VALIDATION
    // ==========================================
    const form = document.getElementById('formEditStitching');
    const errorAlert = document.getElementById('stitchingValidationError');
    const errorMessage = document.getElementById('stitchingValidationMessage');

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        if (errorAlert) errorAlert.style.display = 'none';
    }

    form.addEventListener('input', function (e) {
        if (e.target.classList.contains('is-invalid')) {
            e.target.classList.remove('is-invalid');
            if (form.querySelectorAll('.is-invalid').length === 0 && errorAlert) {
                errorAlert.style.display = 'none';
            }
        }
    });

    form.addEventListener('change', function (e) {
        if (e.target.classList.contains('is-invalid')) {
            e.target.classList.remove('is-invalid');
            if (form.querySelectorAll('.is-invalid').length === 0 && errorAlert) {
                errorAlert.style.display = 'none';
            }
        }
    });

    form.addEventListener('submit', function (e) {
        clearErrors();
        let hasError = false;
        let firstInvalid = null;
        let missingFieldNames = [];

        function markInvalid(elem, fieldName) {
            if (!elem) return;
            elem.classList.add('is-invalid');
            if (!firstInvalid) firstInvalid = elem;
            if (fieldName && !missingFieldNames.includes(fieldName)) {
                missingFieldNames.push(fieldName);
            }
            hasError = true;
        }

        const stitchingName = document.getElementById('stitchingName');
        if (!stitchingName || !stitchingName.value.trim()) {
            markInvalid(stitchingName, 'Stitching Name');
        }

        const selectTailor = document.getElementById('selectTailor');
        if (!selectTailor || !selectTailor.value) {
            markInvalid(selectTailor, 'Tailor / Stitching Master');
        }

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) {
            hasError = true;
            missingFieldNames.push('Variant Items');
        }

        rows.forEach(tr => {
            const stitchQty = tr.querySelector('.input-stitch-qty');
            const perPcWt = tr.querySelector('.input-per-pc-wt');
            const totalWt = tr.querySelector('.input-row-total-wt');
            const basePrice = tr.querySelector('.input-base-price');
            const discPrice = tr.querySelector('.input-disc-price');

            if (!stitchQty || stitchQty.value.trim() === '' || isNaN(stitchQty.value) || Number(stitchQty.value) < 0) {
                markInvalid(stitchQty, 'Stitching Qty');
            }
            if (!perPcWt || perPcWt.value.trim() === '' || isNaN(perPcWt.value) || Number(perPcWt.value) <= 0) {
                markInvalid(perPcWt, 'Per Pc Wt');
            }
            if (!totalWt || totalWt.value.trim() === '' || isNaN(totalWt.value) || Number(totalWt.value) <= 0) {
                markInvalid(totalWt, 'Total Wt');
            }
            if (!basePrice || basePrice.value.trim() === '' || isNaN(basePrice.value) || Number(basePrice.value) < 0) {
                markInvalid(basePrice, 'Base Price');
            }
            if (!discPrice || discPrice.value.trim() === '' || isNaN(discPrice.value) || Number(discPrice.value) < 0) {
                markInvalid(discPrice, 'Discount Price');
            }
        });

        if (hasError) {
            e.preventDefault();
            e.stopPropagation();
            if (errorAlert) {
                errorAlert.style.display = 'flex';
                if (missingFieldNames.length > 0) {
                    errorMessage.textContent = 'Please fill the empty input fields: ' + missingFieldNames.join(', ');
                } else {
                    errorMessage.textContent = 'Please fill in all empty required fields before saving.';
                }
            }
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
            return false;
        }
    });

    // Quick Add Tailor AJAX with Inline Mobile Validation
    const formQuickAddTailor = document.getElementById('formQuickAddTailor');
    const quickTailorPhone = document.getElementById('quickTailorPhone');
    const quickTailorPhoneError = document.getElementById('quickTailorPhoneError');

    function validateTailorPhoneInline() {
        if (!quickTailorPhone) return true;
        const val = quickTailorPhone.value.trim();
        if (!val) {
            quickTailorPhone.classList.remove('is-invalid', 'is-valid');
            quickTailorPhoneError.classList.add('d-none');
            quickTailorPhoneError.textContent = '';
            return true;
        }
        if (!/^[6-9]/.test(val)) {
            quickTailorPhone.classList.add('is-invalid');
            quickTailorPhone.classList.remove('is-valid');
            quickTailorPhoneError.classList.remove('d-none');
            quickTailorPhoneError.textContent = 'Mobile number must start with 6, 7, 8, or 9.';
            return false;
        }
        if (val.length < 10) {
            quickTailorPhone.classList.add('is-invalid');
            quickTailorPhone.classList.remove('is-valid');
            quickTailorPhoneError.classList.remove('d-none');
            quickTailorPhoneError.textContent = `Mobile number must be 10 digits (${val.length}/10 entered).`;
            return false;
        }
        quickTailorPhone.classList.remove('is-invalid');
        quickTailorPhone.classList.add('is-valid');
        quickTailorPhoneError.classList.add('d-none');
        quickTailorPhoneError.textContent = '';
        return true;
    }

    if (quickTailorPhone) {
        quickTailorPhone.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
            validateTailorPhoneInline();
        });
        quickTailorPhone.addEventListener('blur', function() {
            validateTailorPhoneInline();
        });
    }

    if (formQuickAddTailor) {
        formQuickAddTailor.addEventListener('submit', function (e) {
            e.preventDefault();
            const name = document.getElementById('quickTailorName').value.trim();
            const phone = quickTailorPhone ? quickTailorPhone.value.trim() : '';
            const code = document.getElementById('quickTailorCode').value.trim();
            const errDiv = document.getElementById('quickTailorError');

            if (!name) return;

            if (phone && !validateTailorPhoneInline()) {
                quickTailorPhone.focus();
                return;
            }

            const formData = new FormData();
            formData.append('name', name);
            formData.append('phone', phone);
            formData.append('employee_code', code);
            formData.append('cutting_id', <?= (int)($batch['cutting_id'] ?? 0) ?>);

            fetch('<?= \App\Core\Controller::url('/api/stitching/quick-tailor') ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const selectTailor = document.getElementById('selectTailor');
                    const opt = document.createElement('option');
                    opt.value = data.id;
                    opt.textContent = `${data.name} ${data.phone ? '(' + data.phone + ')' : ''}`;
                    opt.selected = true;
                    selectTailor.appendChild(opt);

                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalAddTailor'));
                    if (modal) modal.hide();
                    formQuickAddTailor.reset();
                    errDiv.style.display = 'none';
                } else {
                    errDiv.textContent = data.message || 'Failed to save tailor.';
                    errDiv.style.display = 'block';
                }
            })
            .catch(err => {
                console.error(err);
                errDiv.textContent = 'Network error while saving tailor.';
                errDiv.style.display = 'block';
            });
        });
    }
});
</script>
