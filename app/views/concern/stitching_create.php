<?php
/**
 * View: New Stitching Batch
 * Location: inventory_mekha/app/views/concern/stitching_create.php
 * Matches Image 1 & Image 3 (Stitching Batch Details, Multi-Catalog Dynamic Breakdown, Pricing & Weight Validations)
 */
?>
<div class="container-fluid px-4 py-3">

    <!-- BREADCRUMBS MATCHING IMAGE 1 -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/stitching') ?>" class="text-decoration-none text-muted">Stitching</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">New Stitching Batch</li>
        </ol>
    </nav>

    <div class="mb-3">
        <p class="text-muted small mb-0">
            Select cutting batch by name, dynamically identify multi-catalog hierarchies, and set variant-wise quantities, weights & distinct prices
        </p>
    </div>

    <form action="<?= \App\Core\Controller::url('/production/stitching/store') ?>" method="POST" id="formCreateStitching" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="batch_no" id="inputBatchNo" value="<?= htmlspecialchars($batchNo) ?>">

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
                                   placeholder="E.G. STITCHING - LOT A"
                                   oninput="this.value = this.value.toUpperCase();" required>
                            <div class="invalid-feedback">Please enter a Stitching Name.</div>
                        </div>

                        <!-- Batch Number -->
                        <div class="mb-3">
                            <input type="hidden" name="status" value="in_progress">
                            <label class="form-label fw-semibold text-dark mb-1">Batch Number</label>
                            <input type="text" class="form-control bg-light fw-bold text-secondary font-monospace" 
                                   value="<?= htmlspecialchars($batchNo) ?>" readonly>
                        </div>

                        <!-- Select Cutting Name * -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark mb-1">
                                <i class="bi bi-scissors text-danger me-1"></i> Select Cutting Name <span class="text-danger">*</span>
                            </label>
                            <select name="cutting_id" id="selectCuttingBatch" class="form-select" required>
                                <option value="">-- Choose Cutting Name / Batch --</option>
                                <?php foreach ($cuttingBatches as $cb): ?>
                                    <option value="<?= $cb['id'] ?>" 
                                            data-pieces="<?= (int)$cb['cut_pieces'] ?>"
                                            data-usable="<?= (float)$cb['used_weight'] ?>"
                                            data-batch="<?= htmlspecialchars($cb['batch_no'] ?: $cb['log_no']) ?>"
                                            <?= ($selectedCuttingId == $cb['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($cb['cutting_name'] ? $cb['cutting_name'] . ' (' . ($cb['batch_no'] ?: $cb['log_no']) . ')' : ($cb['batch_no'] ?: $cb['log_no'])) . ' - ' . (int)$cb['cut_pieces'] . ' pcs') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a Cutting Batch.</div>

                            <?php if (empty($cuttingBatches)): ?>
                                <div class="alert alert-warning py-2 px-3 small border-0 d-flex align-items-center gap-2 mt-2">
                                    <i class="bi bi-info-circle fs-5"></i>
                                    <div>All cutting batches have already been assigned to stitching. <a href="<?= \App\Core\Controller::url('/production/cutting/create') ?>" class="fw-bold text-dark text-decoration-underline">Create a cutting batch.</a></div>
                                </div>
                            <?php endif; ?>

                            <!-- Source Cutting Batch Card (Dynamically Shown) -->
                            <div id="cardCuttingSourceDetails" class="card bg-light border-0 rounded-3 p-3 mt-2" style="display:none;">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="fw-bold text-dark font-monospace small" id="lblCuttingBatchCodes">
                                        [PRG-XXXX] [CUT-XXXX]
                                    </div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" id="lblCuttingUsableWeight">
                                        0.00 kg Usable
                                    </span>
                                </div>
                                <div class="text-muted small" id="lblCuttingCatalogs">
                                    Assigned Catalog: Loading...
                                </div>
                            </div>
                        </div>

                        <!-- Tailor / Stitching Master -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold text-dark mb-0">
                                    Tailor / Stitching Master <span class="text-danger">*</span>
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" 
                                        data-bs-toggle="modal" data-bs-target="#modalAddTailor">
                                    <i class="bi bi-plus"></i> Add Tailor
                                </button>
                            </div>
                            <select name="tailor_id" id="selectTailor" class="form-select" required>
                                <option value="">Select Tailor / Stitching Master</option>
                                <?php foreach ($tailors as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name'] . ($t['phone'] ? ' (' . $t['phone'] . ')' : '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a Tailor / Stitching Master.</div>
                        </div>

                        <!-- Notes & Remarks -->
                        <div class="mb-2">
                            <label class="form-label fw-semibold text-dark mb-1">Notes & Remarks</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Stitching instructions, lot details, etc..."></textarea>
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
                                    0 Cut pcs
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1.5 px-2.5" id="badgeStitchedPcs">
                                    0 Stitched pcs
                                </span>
                                <span class="badge bg-light text-secondary border py-1.5 px-2.5" id="badgeCutWt">
                                    Cut Wt: 0.00 kg
                                </span>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-1.5 px-2.5" id="badgeStitchWt">
                                    Stitch Wt: 0.00 kg
                                </span>
                                <span class="badge bg-success text-white py-1.5 px-2.5" id="badgeWeightMatched" style="display:none;">
                                    <i class="bi bi-check2-circle me-1"></i>Weight Matched
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BULK CATALOG PRICING TOOLBAR (Shown when items loaded) -->
                    <div id="pricingToolbar" class="bg-light px-3 py-2.5 border-bottom" style="display:none;">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="small fw-bold text-secondary d-flex align-items-center gap-1 text-nowrap">
                                <i class="bi bi-tags-fill text-primary"></i> Catalog Pricing:
                            </span>
                            <div class="flex-grow-1" style="min-width: 180px; max-width: 280px;">
                                <select id="bulkCatalogSelect" class="form-select form-select-sm">
                                    <option value="">-- All Catalogs in Batch --</option>
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
                        
                        <!-- EMPTY STATE (NO CUTTING SELECTED) -->
                        <div id="emptyStitchingState" class="text-center py-5 px-3">
                            <div class="avatar-lg bg-light rounded-circle mx-auto d-flex align-items-center justify-content-center text-muted mb-3" style="width: 70px; height: 70px;">
                                <i class="bi bi-scissors fs-1"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-1">Select a Cutting Name</h5>
                            <p class="text-muted small max-w-400 mx-auto">
                                Choose a cutting name from the dropdown on the left to dynamically load all cut components, multiple catalog hierarchies, quantities, weights, and pricing.
                            </p>
                        </div>

                        <!-- DYNAMIC VARIANTS TABLE -->
                        <div class="table-responsive" id="tableStitchingWrapper" style="display:none;">
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
                                    <!-- Rendered dynamically via JavaScript -->
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
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnSaveStitching">
                        <i class="bi bi-check2-circle me-1"></i> Save Stitching Batch
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

<!-- JAVASCRIPT LOGIC & DYNAMIC VALIDATION -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectCutting = document.getElementById('selectCuttingBatch');
    const emptyState = document.getElementById('emptyStitchingState');
    const tableWrapper = document.getElementById('tableStitchingWrapper');
    const pricingToolbar = document.getElementById('pricingToolbar');
    const tbody = document.getElementById('tbodyStitchingItems');
    const cardCuttingSource = document.getElementById('cardCuttingSourceDetails');
    const lblCuttingCodes = document.getElementById('lblCuttingBatchCodes');
    const lblCuttingUsableWeight = document.getElementById('lblCuttingUsableWeight');
    const lblCuttingCatalogs = document.getElementById('lblCuttingCatalogs');
    const bulkCatalogSelect = document.getElementById('bulkCatalogSelect');

    const badgeCutPcs = document.getElementById('badgeCutPcs');
    const badgeStitchedPcs = document.getElementById('badgeStitchedPcs');
    const badgeCutWt = document.getElementById('badgeCutWt');
    const badgeStitchWt = document.getElementById('badgeStitchWt');
    const badgeWeightMatched = document.getElementById('badgeWeightMatched');

    const btnCopyCutQty = document.getElementById('btnCopyCutQty');
    const btnApplyBulkPricing = document.getElementById('btnApplyBulkPricing');

    let currentCuttingData = null;
    let itemsList = [];

    // Fetch cutting details on dropdown change
    selectCutting.addEventListener('change', function () {
        const cuttingId = this.value;
        if (!cuttingId) {
            emptyState.style.display = 'block';
            tableWrapper.style.display = 'none';
            pricingToolbar.style.display = 'none';
            cardCuttingSource.style.display = 'none';
            resetBadges();
            return;
        }

        fetch('<?= \App\Core\Controller::url('/api/stitching/cutting-details') ?>?id=' + cuttingId, {
            headers: { 'Accept': 'application/json' }
        })
            .then(async res => {
                let data;
                try {
                    data = await res.json();
                } catch (error) {
                    throw new Error('The server returned an invalid response while loading cutting details.');
                }
                if (!res.ok) {
                    throw new Error(data.message || `Unable to load cutting details (HTTP ${res.status}).`);
                }
                return data;
            })
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Failed to load cutting batch details.');
                    return;
                }

                currentCuttingData = data.cutting;
                itemsList = data.items || [];

                // Display source cutting batch card
                cardCuttingSource.style.display = 'block';
                lblCuttingCodes.textContent = `[${data.cutting.program_no || 'PRG'}] [${data.cutting.batch_no || 'CUT'}]`;
                lblCuttingUsableWeight.textContent = `${parseFloat(data.cutting.usable_weight || 0).toFixed(2)} kg Usable`;

                const cats = data.catalogs || [];
                if (cats.length > 0) {
                    const badges = cats.map(c => 
                        `<span class="badge bg-primary text-white me-1.5 mb-1 px-2 py-1 font-monospace shadow-2xs">${escapeHtml(c.name)}${c.style_code ? ' [' + escapeHtml(c.style_code) + ']' : ''}</span>`
                    ).join('');
                    lblCuttingCatalogs.innerHTML = `<strong>Assigned Catalogs (${cats.length}):</strong><div class="mt-1 d-flex flex-wrap gap-1">${badges}</div>`;
                } else {
                    lblCuttingCatalogs.innerHTML = `<strong>Assigned Catalog:</strong> <span class="badge bg-secondary text-white ms-1">Standard</span>`;
                }

                // Populate bulk catalog dropdown
                bulkCatalogSelect.innerHTML = '<option value="">-- All Catalogs in Batch --</option>';
                cats.forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat.id;
                    opt.textContent = `${cat.name} (${cat.style_code || 'CAT'})`;
                    opt.dataset.base = cat.base_price || 0;
                    opt.dataset.disc = cat.discount_price || 0;
                    bulkCatalogSelect.appendChild(opt);
                });

                renderItemsTable(itemsList);
                emptyState.style.display = 'none';
                tableWrapper.style.display = 'block';
                pricingToolbar.style.display = 'block';

                recalculateTotals();
            })
            .catch(err => {
                console.error(err);
                alert(err.message || 'Unable to load cutting details. Please try again.');
            });
    });

    // If an initial cutting batch is selected, trigger change
    if (selectCutting.value) {
        selectCutting.dispatchEvent(new Event('change'));
    }

    function renderItemsTable(items) {
        tbody.innerHTML = '';
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No cut items found for this cutting batch.</td></tr>';
            return;
        }

        items.forEach((it, idx) => {
            const tr = document.createElement('tr');
            tr.dataset.catalogId = it.catalog_id || '';
            tr.dataset.idx = idx;

            tr.innerHTML = `
                <td class="ps-3 py-2">
                    <input type="hidden" name="items[${idx}][cutting_item_id]" value="${it.cutting_item_id || ''}">
                    <input type="hidden" name="items[${idx}][catalog_id]" value="${it.catalog_id || ''}">
                    <input type="hidden" name="items[${idx}][variant_id]" value="${it.variant_id || ''}">
                    <input type="hidden" name="items[${idx}][size]" value="${escapeHtml(it.size || '')}">
                    <input type="hidden" name="items[${idx}][color]" value="${escapeHtml(it.color || '')}">
                    <input type="hidden" name="items[${idx}][product_code]" value="${escapeHtml(it.product_code || '')}">
                    <input type="hidden" name="items[${idx}][variant_name]" value="${escapeHtml(it.variant_name || '')}">
                    <input type="hidden" name="items[${idx}][cut_qty]" class="input-cut-qty" value="${it.cut_qty || 0}">

                    <div class="d-flex flex-column">
                        <span class="badge bg-light text-dark border align-self-start mb-1 text-truncate" style="max-width: 250px;">
                            <i class="bi bi-tag text-primary me-1"></i> ${escapeHtml(it.catalog_name || 'Catalog Item')}${it.catalog_code ? ` <span class="badge bg-secondary-subtle text-secondary border font-monospace ms-1" style="font-size:0.65rem;">${escapeHtml(it.catalog_code)}</span>` : ''}
                        </span>
                        <div class="fw-bold text-dark small">
                            ${escapeHtml(it.variant_name || (it.size + ' - ' + it.color))}
                        </div>
                        <div class="text-muted font-monospace" style="font-size: 0.75rem;">
                            Product Code: ${escapeHtml(it.product_code || 'N/A')}
                        </div>
                    </div>
                </td>
                <td class="text-center py-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-6 font-monospace">
                        ${it.cut_qty || 0} pcs
                    </span>
                </td>
                <td class="text-center py-2">
                    <input type="number" name="items[${idx}][stitching_qty]" 
                           class="form-control form-control-sm text-center fw-bold input-stitch-qty" 
                           value="${it.stitching_qty || 0}" min="0" required placeholder="0">
                    <div class="invalid-feedback">Required</div>
                </td>
                <td class="text-center py-2">
                    <input type="number" step="0.001" name="items[${idx}][per_pc_weight]" 
                           class="form-control form-control-sm text-center font-monospace input-per-pc-wt" 
                           value="${parseFloat(it.per_pc_weight || 0).toFixed(3)}" min="0.001" required placeholder="0.000">
                    <div class="invalid-feedback">Required</div>
                </td>
                <td class="text-center py-2">
                    <input type="number" step="0.01" name="items[${idx}][total_weight]" 
                           class="form-control form-control-sm text-center font-monospace bg-light fw-bold input-row-total-wt" 
                           value="${parseFloat(it.total_weight || 0).toFixed(2)}" min="0.01" required placeholder="0.00">
                    <div class="invalid-feedback">Required</div>
                </td>
                <td class="text-center py-2">
                    <div class="input-group input-group-sm has-validation">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" name="items[${idx}][base_price]" 
                               class="form-control form-control-sm text-end input-base-price" 
                               value="${parseFloat(it.base_price || 0).toFixed(2)}" min="0" required placeholder="0.00">
                        <div class="invalid-feedback">Required</div>
                    </div>
                </td>
                <td class="text-center pe-3 py-2">
                    <div class="input-group input-group-sm has-validation">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" name="items[${idx}][discount_price]" 
                               class="form-control form-control-sm text-end input-disc-price" 
                               value="${parseFloat(it.discount_price || 0).toFixed(2)}" min="0" required placeholder="0.00">
                        <div class="invalid-feedback">Required</div>
                    </div>
                </td>
            `;

            tbody.appendChild(tr);
        });

        attachRowEvents();
    }

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

    // Bulk Catalog Select Change -> pre-fill pricing & highlight rows
    bulkCatalogSelect.addEventListener('change', function () {
        const selectedId = this.value;
        const opt = this.options[this.selectedIndex];
        if (opt && opt.value) {
            const base = parseFloat(opt.dataset.base || 0);
            const disc = parseFloat(opt.dataset.disc || 0);
            if (base > 0) document.getElementById('bulkBasePrice').value = base.toFixed(2);
            if (disc > 0) document.getElementById('bulkDiscPrice').value = disc.toFixed(2);
        }

        // Highlight matching rows in table
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(tr => {
            if (!selectedId || tr.dataset.catalogId === selectedId) {
                tr.style.opacity = '1';
                tr.classList.remove('table-light');
            } else {
                tr.style.opacity = '0.45';
                tr.classList.add('table-light');
            }
        });
    });

    // Apply Bulk Pricing to Table
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

        recalculateTotals();
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

        const cutWeight = currentCuttingData ? parseFloat(currentCuttingData.usable_weight || 0) : 0;

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

    function resetBadges() {
        badgeCutPcs.textContent = '0 Cut pcs';
        badgeStitchedPcs.textContent = '0 Stitched pcs';
        badgeCutWt.textContent = 'Cut Wt: 0.00 kg';
        badgeStitchWt.textContent = 'Stitch Wt: 0.00 kg';
        badgeWeightMatched.style.display = 'none';
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

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
        formData.append('cutting_id', document.getElementById('selectCuttingBatch').value);

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
                modal.hide();
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

    // ==========================================
    // CLIENT-SIDE FORM VALIDATION
    // ==========================================
    const form = document.getElementById('formCreateStitching');
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

        const selectCuttingBatch = document.getElementById('selectCuttingBatch');
        if (!selectCuttingBatch || !selectCuttingBatch.value) {
            markInvalid(selectCuttingBatch, 'Source Cutting Batch');
        }

        const selectTailor = document.getElementById('selectTailor');
        if (!selectTailor || !selectTailor.value) {
            markInvalid(selectTailor, 'Tailor / Stitching Master');
        }

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) {
            hasError = true;
            missingFieldNames.push('Cutting Batch Variant Items');
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
});
</script>
