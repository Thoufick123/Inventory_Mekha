<?php
// app/views/concern/program_create.php - Create Production Program with Multi-Roll Allocation & Out-of-Qty Tracking
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs matching Image 2 -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Production</li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog?tab=programs') ?>" class="text-decoration-none text-muted">Programs</a></li>
            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page">Create Program</li>
        </ol>
    </nav>

    <!-- Header Section matching Image 2 -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2.5 rounded-3 bg-primary-subtle text-primary border border-primary-subtle shadow-xs">
                <i class="bi bi-calendar-plus fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Create Production Program</h4>
            </div>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/production/catalog?tab=programs') ?>" class="btn btn-outline-secondary rounded-3 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                <i class="bi bi-arrow-left"></i> Back to Programs
            </a>
        </div>
    </div>

    <!-- Main Form matching Image 2 layout -->
    <form action="<?= \App\Core\Controller::url('/production/programs/store') ?>" method="POST" id="formCreateProgram" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

        <div class="row g-4">
            <!-- ========================================== -->
            <!-- LEFT COLUMN: SECTION 1 & SECTION 2         -->
            <!-- ========================================== -->
            <div class="col-lg-7">
                
                <!-- 1. PROGRAM OVERVIEW -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-tag-fill text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark">1. Program Overview</h6>
                        </div>
                        <small class="text-secondary" style="font-size: 0.78rem;">Define the unique program title and reference code.</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">Program Name <span class="text-danger">*</span></label>
                            <input type="text" name="program_name" id="inputProgramName" class="form-control" placeholder="E.G. SUMMER MATERNITY COLLECTION 2026" required autofocus>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Enter a descriptive title for this manufacturing run.</small>
                            <div class="invalid-feedback">Program title is required (minimum 3 characters).</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Program Code / Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted font-monospace">#</span>
                                    <input type="text" name="program_no" id="inputProgramNo" class="form-control font-monospace fw-bold bg-light" value="<?= htmlspecialchars($suggestedCode) ?>" readonly required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Person Name (Planner / Creator) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person-fill"></i></span>
                                    <input type="text" name="planner_name" class="form-control bg-light fw-semibold" value="<?= htmlspecialchars($plannerName) ?>" readonly required>
                                </div>
                                <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Person creating or planning this production program.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. RAW MATERIAL ROLLS & PER-ROLL CATALOG HIERARCHY -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-layers-fill text-primary"></i>
                                <h6 class="mb-0 fw-bold text-dark">2. Raw Material Rolls & Per-Roll Catalog Hierarchy</h6>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <button type="button" class="btn btn-sm btn-outline-success rounded-3 fw-semibold" id="btnFocusScanner">
                                <i class="bi bi-upc-scan me-1"></i> Scan Roll
                            </button>
                            <button type="button" class="btn btn-sm btn-primary rounded-3 fw-semibold shadow-xs" id="btnAddRollCard">
                                <i class="bi bi-plus-circle me-1"></i> Add Roll
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        
                        <!-- Scanner Banner matching Image 2 -->
                        <div class="p-3 rounded-3 border mb-4" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                            <div class="d-flex justify-content-between align-items-center mb-1.5 flex-wrap gap-1">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-upc-scan text-success fs-5"></i>
                                    <span class="fw-bold small text-dark">Scan Fabric Roll Barcode / QR Tag</span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">Auto-Add Ready</span>
                                </div>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-success"><i class="bi bi-qr-code-scan"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="scanRollInput" placeholder="Scan roll QR or type piece tag (e.g. FB1126141) and press Enter to auto-add..." autocomplete="off">
                                <button type="button" class="btn btn-success fw-semibold px-3" id="btnTriggerScan">
                                    <i class="bi bi-check2-circle me-1"></i> Scan / Add
                                </button>
                            </div>
                            <div class="small text-danger mt-1 d-none" id="scanErrorMsg"></div>
                        </div>

                        <!-- Dynamic Rolls Container -->
                        <div id="rollCardsContainer" class="d-flex flex-column gap-3">
                            <!-- Populated dynamically via JavaScript with Roll #1 -->
                        </div>

                        <div class="small text-danger mt-2 d-none" id="rollValidationMsg">
                            <i class="bi bi-exclamation-circle me-1"></i> Please assign at least one raw material fabric roll with valid quantity.
                        </div>
                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- RIGHT COLUMN: SECTION 3 TIMELINE           -->
            <!-- (SECTION 4 OMITTED AS HIGHLIGHTED IN RED)  -->
            <!-- ========================================== -->
            <div class="col-lg-5">
                
                <!-- 3. PROGRAMMING TIMELINE & SCHEDULING -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-calendar-range-fill text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark">3. Programming Timeline & Scheduling</h6>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">Programming Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="inputStartDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-dark mb-1">Target Completion / End Date <span class="text-danger">*</span></label>
                            <input type="date" name="target_date" id="inputTargetDate" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                            <div class="invalid-feedback">Target date cannot precede start date.</div>
                        </div>
                    </div>
                </div>

                <!-- LIVE FABRIC ROLLS SUMMARY CARD -->
                <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-calculator text-primary"></i> Program Allocation Summary
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small text-secondary">Total Rolls Assigned:</span>
                            <span class="fw-bold font-monospace text-dark" id="summaryTotalRolls">0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small text-secondary">Total Allocated Qty:</span>
                            <span class="fw-bold font-monospace text-primary fs-6" id="summaryTotalQty">0.00 kg</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small text-secondary">Total Fabric Weight:</span>
                            <span class="fw-bold font-monospace text-info fs-6" id="summaryTotalWeight">0.00 kg</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="small text-secondary">Estimated Fabric Value:</span>
                            <span class="fw-bold font-monospace text-success fs-6" id="summaryTotalValue">₹0.00</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- BOTTOM ACTIONS BAR matching Image 2 -->
        <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded-3 shadow-sm border mt-2">
            <a href="<?= \App\Core\Controller::url('/production/catalog?tab=programs') ?>" class="btn btn-light border px-4 py-2 fw-semibold text-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" id="btnSaveProgram">
                <i class="bi bi-check2-circle fs-5"></i> Save Production Program
            </button>
        </div>
    </form>
</div>

<script>
// Available Rolls JSON data from backend with out-of-qty calculations
const availableRollsData = <?= json_encode($availableRolls, JSON_UNESCAPED_UNICODE) ?>;
const categoriesData = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
const catalogsData = <?= json_encode($catalogs, JSON_UNESCAPED_UNICODE) ?>;
const CATALOGS = catalogsData;

let rollCardCounter = 0;

// Dynamic effective balance calculation across all roll cards for a given roll
function getEffectiveRollStock(rollItemId, currentCardElementOrIndex = null) {
    const roll = availableRollsData.find(r => String(r.id) === String(rollItemId));
    if (!roll) return { totalAvailable: 0, allocatedInOthers: 0, remainingForCard: 0, unit: 'pcs' };

    const totalAvailable = parseFloat(roll.remaining_qty || 0);
    const unit = roll.unit || 'kg';

    let allocatedInOthers = 0;
    const cards = document.querySelectorAll('.roll-card');
    cards.forEach(card => {
        if (currentCardElementOrIndex !== null && currentCardElementOrIndex !== undefined) {
            if (typeof currentCardElementOrIndex === 'object' && card === currentCardElementOrIndex) return;
            const idx = card.getAttribute('data-index');
            if (String(idx) === String(currentCardElementOrIndex)) return;
        }
        const sel = card.querySelector('.select-roll-item');
        const qtyInp = card.querySelector('.input-allocated-qty');
        if (sel && String(sel.value) === String(rollItemId) && qtyInp) {
            const val = parseFloat(qtyInp.value);
            if (!isNaN(val) && val > 0) {
                allocatedInOthers += val;
            }
        }
    });

    const remainingForCard = Math.max(0, totalAvailable - allocatedInOthers);
    return {
        totalAvailable,
        allocatedInOthers,
        remainingForCard,
        unit
    };
}

function createRollCardHtml(index, preselectedRollId = null) {
    // Group rolls by specification (Design/Item Name & Color) matching Purchase Stocks Consolidated Registry
    const specGroups = {};
    availableRollsData.forEach(r => {
        const specName = (r.design_name || r.pattern || r.item_name || 'Fabric Roll').toUpperCase();
        const specColor = (r.color || 'STANDARD').toUpperCase();
        const groupKey = `${specName} • ${specColor}`;
        if (!specGroups[groupKey]) {
            specGroups[groupKey] = {
                title: groupKey,
                totalStock: r.design_accumulated_stock || r.remaining_qty,
                unit: r.unit || 'kg',
                unitPrice: r.unit_price || 0,
                billNo: r.bill_no || '',
                supplier: r.supplier_display || '',
                rolls: []
            };
        }
        specGroups[groupKey].rolls.push(r);
    });

    let rollOptionsHtml = '<option value="">-- Select Fabric Roll --</option>';
    for (const [key, grp] of Object.entries(specGroups)) {
        const groupBill = grp.billNo ? ` • Bill #${grp.billNo}` : '';
        const groupSup = grp.supplier ? ` • ${grp.supplier}` : '';
        const groupRate = grp.unitPrice > 0 ? ` • ₹${grp.unitPrice.toFixed(2)}` : '';
        const optgroupLabel = `${grp.title} (Accumulated Stock: ${grp.totalStock.toFixed(2)} ${grp.unit}${groupRate}${groupBill}${groupSup})`;

        rollOptionsHtml += `<optgroup label="${optgroupLabel}">`;
        grp.rolls.forEach((r, bIdx) => {
            const isSel = (preselectedRollId && String(preselectedRollId) === String(r.id)) ? 'selected' : '';
            const tag = r.tag_code ? `[${r.tag_code}] ` : (r.item_code ? `[${r.item_code}] ` : `Roll #${r.id} `);
            const col = r.color ? ` - ${r.color}` : '';
            const price = r.unit_price > 0 ? ` • ₹${r.unit_price.toFixed(2)}` : '';
            const stockStr = ` • Stock: ${r.remaining_qty.toFixed(2)} ${r.unit || 'kg'}`;
            const billInfo = r.bill_no ? ` • Bill #${r.bill_no}` : '';
            const supInfo = r.supplier_display ? ` • ${r.supplier_display}` : '';
            const batchNum = grp.rolls.length > 1 ? ` (Batch #${bIdx + 1} of ${grp.totalStock.toFixed(2)} ${grp.unit})` : '';

            rollOptionsHtml += `
                <option value="${r.id}" 
                        data-id="${r.id}"
                        data-code="${r.tag_code || (r.item_code || '')}" 
                        data-name="${r.item_name || ''}" 
                        data-pattern="${r.pattern || ''}" 
                        data-color="${r.color || ''}" 
                        data-unit="${r.unit || 'kg'}" 
                        data-total="${r.total_qty}" 
                        data-allocated="${r.allocated_qty}" 
                        data-remaining="${r.remaining_qty}"
                        data-stock="${r.remaining_qty}"
                        data-accumulated="${r.design_accumulated_stock || r.remaining_qty}"
                        data-weight="${r.weight || 0.00}"
                        data-price="${r.unit_price || 0.00}"
                        data-catalog-id="${r.catalog_id || ''}"
                        data-catalog-code="${r.catalog_style_code || ''}"
                        data-catalog-name="${r.catalog_name || ''}"
                        data-category-id="${r.category_id || ''}"
                        data-subcategory-id="${r.subcategory_id || ''}"
                        data-subsubcategory-id="${r.sub_sub_category_id || ''}"
                        data-hierarchy="${r.hierarchy_path || ''}"
                        ${isSel}>
                    ${tag}${r.item_name}${col}${stockStr}${batchNum}${price}${billInfo}${supInfo}
                </option>
            `;
        });
        rollOptionsHtml += `</optgroup>`;
    }

    let catOptionsHtml = '<option value="">-- Select Main Category --</option>';
    categoriesData.forEach(c => {
        catOptionsHtml += `<option value="${c.id}" data-name="${c.name}">${c.name}</option>`;
    });

    let catlgOptionsHtml = '<option value="">-- Select Product Style (Optional) --</option>';
    catalogsData.forEach(cl => {
        catlgOptionsHtml += `<option value="${cl.id}">${cl.style_code ? `[${cl.style_code}] ` : ''}${cl.name}</option>`;
    });

    return `
        <div class="card border rounded-3 p-3 bg-white roll-card shadow-xs" id="rollCard_${index}" data-index="${index}">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2.5 py-1">Roll #${index}</span>
                <button type="button" class="btn btn-sm btn-outline-danger py-0.5 px-2 btn-remove-roll" title="Remove this roll">
                    <i class="bi bi-trash me-1"></i> Remove
                </button>
            </div>

            <!-- 1. Select Raw Material Roll * -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-dark mb-1">Select Raw Material Roll <span class="text-danger">*</span></label>
                <select name="rolls[${index}][purchase_bill_item_id]" class="form-select select-roll-item" required>
                    ${rollOptionsHtml}
                </select>
            </div>

            <!-- 2. Roll Qty, Roll Weight, Roll Price matching Image 1 -->
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-dark mb-0">Roll Qty (<span class="roll-unit-label">pcs</span>) <span class="text-danger">*</span></label>
                        <span class="roll-stock-badge text-muted small" style="font-size: 0.72rem;">Stock: 0 pcs</span>
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-box-seam"></i></span>
                        <input type="number" step="1" min="1" name="rolls[${index}][allocated_qty]" class="form-control input-allocated-qty" placeholder="Select Roll" required>
                        <span class="input-group-text bg-light text-muted roll-unit-text">pcs</span>
                    </div>
                    <small class="text-danger small d-none roll-exceed-msg" style="font-size: 0.70rem;">Exceeds available stock!</small>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark mb-1">Roll Weight (kg)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-speedometer2"></i></span>
                        <input type="number" step="0.01" min="0.00" name="rolls[${index}][roll_weight]" class="form-control input-roll-weight bg-light fw-semibold" value="0.00" readonly tabindex="-1" title="Calculated dynamically based on Roll Qty">
                        <span class="input-group-text bg-light text-muted">kg</span>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark mb-1">Roll Price (₹)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted">₹</span>
                        <input type="number" step="0.01" min="0.00" name="rolls[${index}][roll_price]" class="form-control input-roll-price font-monospace bg-light fw-bold" value="0.00" readonly tabindex="-1" title="Calculated dynamically based on Roll Qty">
                    </div>
                </div>
            </div>

            <!-- Hidden target pieces input so calculations remain compatible -->
            <input type="hidden" name="rolls[${index}][target_pieces]" class="input-target-pieces" value="0">

            <!-- 3. Catalog Hierarchy for this Roll matching Image 1 -->
            <div class="card border rounded-3 p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-1.5 small fw-bold text-primary">
                        <i class="bi bi-diagram-3"></i>
                        <span>Catalog Hierarchy for this Roll</span>
                    </div>
                    <span class="badge bg-white text-secondary border roll-catalog-badge">No Catalog Selected</span>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Main Category</label>
                        <select name="rolls[${index}][category_id]" class="form-select form-select-sm select-roll-cat">
                            ${catOptionsHtml}
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Sub Category</label>
                        <select name="rolls[${index}][subcategory_id]" class="form-select form-select-sm select-roll-subcat">
                            <option value="">-- Select Category First --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Sub Sub Category</label>
                        <select name="rolls[${index}][sub_sub_category_id]" class="form-select form-select-sm select-roll-subsubcat">
                            <option value="">-- Select Sub Category First --</option>
                        </select>
                    </div>
                </div>

                <div class="d-none">
                    <label class="form-label small text-muted mb-1">Product Style / Catalog Style</label>
                    <select name="rolls[${index}][catalog_id]" class="form-select form-select-sm select-roll-catalog">
                        ${catlgOptionsHtml}
                    </select>
                </div>
            </div>
        </div>
    `;
}

document.addEventListener('DOMContentLoaded', function() {
    const rollCardsContainer = document.getElementById('rollCardsContainer');
    const scanRollInput = document.getElementById('scanRollInput');
    const scanErrorMsg = document.getElementById('scanErrorMsg');
    const rollValidationMsg = document.getElementById('rollValidationMsg');

    // Add first roll card initially
    addRollCard();

    function addRollCard(preselectedRollId = null) {
        rollCardCounter++;
        const cardHtml = createRollCardHtml(rollCardCounter, preselectedRollId);
        rollCardsContainer.insertAdjacentHTML('beforeend', cardHtml);
        const cardElement = document.getElementById(`rollCard_${rollCardCounter}`);
        bindRollCardEvents(cardElement);

        if (preselectedRollId) {
            const selectEl = cardElement.querySelector('.select-roll-item');
            selectEl.value = preselectedRollId;
            selectEl.dispatchEvent(new Event('change'));
        }
        updateSummary();
        updateAllCreateCardsStock();
    }

    document.getElementById('btnAddRollCard').addEventListener('click', function() {
        addRollCard();
    });

    document.getElementById('btnFocusScanner').addEventListener('click', function() {
        scanRollInput.focus();
        scanRollInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    function bindRollCardEvents(card) {
        const rollSelect = card.querySelector('.select-roll-item');
        const stockBadge = card.querySelector('.roll-stock-badge');
        const unitLabels = card.querySelectorAll('.roll-unit-label, .roll-unit-text');
        const allocatedInput = card.querySelector('.input-allocated-qty');
        const weightInput = card.querySelector('.input-roll-weight');
        const priceInput = card.querySelector('.input-roll-price');
        const exceedMsg = card.querySelector('.roll-exceed-msg');
        const removeBtn = card.querySelector('.btn-remove-roll');

        const catalogBadge = card.querySelector('.roll-catalog-badge');
        const catSelect = card.querySelector('.select-roll-cat');
        const subcatSelect = card.querySelector('.select-roll-subcat');
        const subsubcatSelect = card.querySelector('.select-roll-subsubcat');
        const catalogSelect = card.querySelector('.select-roll-catalog');

        // Roll selection change -> auto-populate Qty, Weight, Price, and Catalog
        rollSelect.addEventListener('change', function() {
            const opt = this.selectedOptions[0];
            if (!opt || !opt.value) {
                stockBadge.textContent = 'Stock: 0 pcs';
                stockBadge.className = 'roll-stock-badge text-muted small';
                unitLabels.forEach(el => el.textContent = 'pcs');
                allocatedInput.value = '';
                weightInput.value = '0.00';
                priceInput.value = '0.00';
                catalogBadge.textContent = 'No Catalog Selected';
                catalogBadge.className = 'badge bg-white text-secondary border roll-catalog-badge';
                catSelect.value = '';
                subcatSelect.innerHTML = '<option value="">-- Select Category First --</option>';
                subsubcatSelect.innerHTML = '<option value="">-- Select Sub Category First --</option>';
                catalogSelect.value = '';
                exceedMsg.classList.add('d-none');
                allocatedInput.classList.remove('is-invalid');
                updateAllCreateCardsStock(card);
                updateSummary();
                return;
            }

            const rollId = opt.value;
            const stockInfo = getEffectiveRollStock(rollId, card);
            const stock = stockInfo.remainingForCard;
            const unit = stockInfo.unit || opt.getAttribute('data-unit') || 'kg';
            const weight = parseFloat(opt.getAttribute('data-weight') || '0');
            const price = parseFloat(opt.getAttribute('data-price') || '0');

            // 1. Update Unit Labels
            unitLabels.forEach(el => el.textContent = unit);

            // 2. Update Stock Badge with dynamic remaining balance
            if (stock > 0) {
                if (stockInfo.allocatedInOthers > 0) {
                    stockBadge.innerHTML = `Stock: <strong class="text-success">${stock.toFixed(2)} ${unit}</strong> <span class="text-muted small">(${stockInfo.allocatedInOthers.toFixed(2)} in other rolls)</span>`;
                } else {
                    stockBadge.innerHTML = `Stock: <strong class="text-success">${stock.toFixed(2)} ${unit}</strong>`;
                }
            } else {
                stockBadge.innerHTML = `Stock: <strong class="text-danger">0.00 ${unit} (Fully Allocated)</strong>`;
            }

            // 3. Auto-populate Roll Qty (Strict Integer only)
            const intStock = Math.floor(stock);
            if (!allocatedInput.value || parseInt(allocatedInput.value, 10) <= 0) {
                allocatedInput.value = intStock > 0 ? intStock : (stock > 0 ? 1 : '0');
            }
            allocatedInput.max = intStock > 0 ? intStock : '1';
            allocatedInput.step = '1';
            allocatedInput.min = '1';

            // 4 & 5. Dynamically calculate Weight & Price based on selected Qty
            calculateCardWeightAndPrice();

            // Catalog hierarchy is selected explicitly for each program.
            catalogSelect.value = '';
            catalogBadge.textContent = 'No Catalog Selected';
            catalogBadge.className = 'badge bg-white text-secondary border roll-catalog-badge';
            catSelect.value = '';
            subcatSelect.innerHTML = '<option value="">-- Select Category First --</option>';
            subsubcatSelect.innerHTML = '<option value="">-- Select Sub Category First --</option>';

            validateRollAllocation(allocatedInput, stock, exceedMsg);
            updateAllCreateCardsStock(card);
            updateSummary();
        });

        function calculateCardWeightAndPrice() {
            const opt = rollSelect.selectedOptions[0];
            if (!opt || !opt.value) {
                weightInput.value = '0.00';
                priceInput.value = '0.00';
                return;
            }

            const qty = parseInt(allocatedInput.value, 10) || 0;
            const unit = (opt.getAttribute('data-unit') || 'kg').toLowerCase();
            const totalQty = parseFloat(opt.getAttribute('data-total') || '0');
            const totalWeight = parseFloat(opt.getAttribute('data-weight') || '0');
            const unitRate = parseFloat(opt.getAttribute('data-price') || '0');

            // 1. Dynamic Weight Calculation based on selected Qty
            let calcWeight = 0;
            if (unit === 'kg') {
                calcWeight = qty;
            } else if (totalQty > 0 && totalWeight > 0) {
                calcWeight = (qty / totalQty) * totalWeight;
            } else if (totalWeight > 0) {
                calcWeight = totalWeight;
            }
            weightInput.value = calcWeight.toFixed(2);

            // 2. Dynamic Price Calculation based on selected Qty
            let calcPrice = 0;
            if (unitRate > 0 && qty > 0) {
                calcPrice = qty * unitRate;
            }
            priceInput.value = calcPrice.toFixed(2);
        }

        allocatedInput.addEventListener('keydown', function(e) {
            if (e.key === '.' || e.key === ',' || e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-') {
                e.preventDefault();
            }
        });

        // Allocated Qty input change -> dynamic recalculation of weight & price + validation
        allocatedInput.addEventListener('input', function() {
            let val = this.value;
            if (val.includes('.') || val.includes(',')) {
                val = Math.floor(parseFloat(val)) || '';
                this.value = val;
            }
            if (val !== '' && parseInt(val, 10) < 1) {
                this.value = 1;
            }
            calculateCardWeightAndPrice();
            const opt = rollSelect.selectedOptions[0];
            const rollId = opt ? opt.value : null;
            const stockInfo = rollId ? getEffectiveRollStock(rollId, card) : null;
            const stock = stockInfo ? stockInfo.remainingForCard : 0;

            validateRollAllocation(this, stock, exceedMsg);
            updateAllCreateCardsStock(card);
            updateSummary();
        });

        weightInput.addEventListener('input', updateSummary);
        priceInput.addEventListener('input', updateSummary);

        // Catalog selection change
        catalogSelect.addEventListener('change', function() {
            const opt = this.selectedOptions[0];
            if (opt && opt.value) {
                catalogBadge.textContent = opt.textContent.trim();
                catalogBadge.className = 'badge bg-primary text-white border-0 roll-catalog-badge';
            } else {
                updateHierarchyBadgeFromCats(catSelect, subcatSelect, subsubcatSelect, catalogBadge, catalogSelect);
            }
        });

        // 3-Tier category cascade
        catSelect.addEventListener('change', function() {
            loadSubcatsForCard(catSelect, subcatSelect, subsubcatSelect);
            updateHierarchyBadgeFromCats(catSelect, subcatSelect, subsubcatSelect, catalogBadge, catalogSelect);
        });

        subcatSelect.addEventListener('change', function() {
            loadSubSubcatsForCard(subcatSelect, subsubcatSelect);
            updateHierarchyBadgeFromCats(catSelect, subcatSelect, subsubcatSelect, catalogBadge, catalogSelect);
        });

        subsubcatSelect.addEventListener('change', function() {
            updateHierarchyBadgeFromCats(catSelect, subcatSelect, subsubcatSelect, catalogBadge, catalogSelect);
        });

        // Remove roll button
        removeBtn.addEventListener('click', function() {
            const totalCards = rollCardsContainer.querySelectorAll('.roll-card').length;
            if (totalCards <= 1) {
                alert('A production program must have at least one roll assigned.');
                return;
            }
            card.remove();
            renumberRollCards();
            updateAllCreateCardsStock();
            updateSummary();
        });
    }

    function updateAllCreateCardsStock(skipCard = null) {
        const cards = rollCardsContainer.querySelectorAll('.roll-card');
        cards.forEach(c => {
            const sel = c.querySelector('.select-roll-item');
            const input = c.querySelector('.input-allocated-qty');
            const stockBadge = c.querySelector('.roll-stock-badge');
            const exceedMsg = c.querySelector('.roll-exceed-msg');

            if (!sel || !sel.value) return;

            const rollId = sel.value;
            const stockInfo = getEffectiveRollStock(rollId, c);
            const rem = stockInfo.remainingForCard;
            const unit = stockInfo.unit;

            if (stockBadge) {
                if (rem > 0) {
                    if (stockInfo.allocatedInOthers > 0) {
                        stockBadge.innerHTML = `Stock: <strong class="text-success">${rem.toFixed(2)} ${unit}</strong> <span class="text-muted small">(${stockInfo.allocatedInOthers.toFixed(2)} in other rolls)</span>`;
                    } else {
                        stockBadge.innerHTML = `Stock: <strong class="text-success">${rem.toFixed(2)} ${unit}</strong>`;
                    }
                } else {
                    stockBadge.innerHTML = `Stock: <strong class="text-danger">0.00 ${unit} (Fully Allocated)</strong>`;
                }
            }

            if (input) {
                input.setAttribute('max', Math.floor(rem) > 0 ? Math.floor(rem) : '1');
                input.setAttribute('step', '1');
                input.setAttribute('min', '1');
            }

            if (skipCard === null || c !== skipCard) {
                if (input && input.value !== '') {
                    validateRollAllocation(input, rem, exceedMsg);
                }
            }
        });

        // Update option labels in all select dropdowns
        cards.forEach(c => {
            const sel = c.querySelector('.select-roll-item');
            if (sel) {
                Array.from(sel.options).forEach(opt => {
                    if (!opt.value) return;
                    const optRollId = opt.value;
                    const baseRoll = availableRollsData.find(r => String(r.id) === String(optRollId));
                    if (!baseRoll) return;

                    const info = getEffectiveRollStock(optRollId, c);
                    opt.dataset.remaining = info.remainingForCard;

                    const tag = baseRoll.tag_code ? `[${baseRoll.tag_code}] ` : (baseRoll.item_code ? `[${baseRoll.item_code}] ` : `Roll #${baseRoll.id} `);
                    const col = baseRoll.color ? ` - ${baseRoll.color}` : '';
                    const price = baseRoll.unit_price > 0 ? ` • ₹${parseFloat(baseRoll.unit_price).toFixed(2)}` : '';
                    const billInfo = baseRoll.bill_no ? ` • Bill #${baseRoll.bill_no}` : '';
                    const supInfo = baseRoll.supplier_display ? ` • ${baseRoll.supplier_display}` : '';

                    let stockStr = '';
                    if (info.remainingForCard <= 0) {
                        stockStr = ` • OUT OF STOCK (0.00 ${baseRoll.unit || 'kg'})`;
                    } else if (info.allocatedInOthers > 0) {
                        stockStr = ` • Stock: ${info.remainingForCard.toFixed(2)} ${baseRoll.unit || 'kg'} (Bal)`;
                    } else {
                        stockStr = ` • Stock: ${info.remainingForCard.toFixed(2)} ${baseRoll.unit || 'kg'}`;
                    }

                    opt.text = `${tag}${baseRoll.item_name}${col}${stockStr}${price}${billInfo}${supInfo}`;
                });
            }
        });
    }

    function loadSubcatsForCard(catSelect, subcatSelect, subsubcatSelect, preselectSubId = null, preselectSubSubId = null) {
        const catId = catSelect.value;
        subcatSelect.innerHTML = '<option value="">Loading...</option>';
        subsubcatSelect.innerHTML = '<option value="">-- Select Sub Category First --</option>';

        if (!catId) {
            subcatSelect.innerHTML = '<option value="">-- Select Category First --</option>';
            return;
        }

        fetch('<?= \App\Core\Controller::url('/api/categories/subcategories?category_id=') ?>' + encodeURIComponent(catId))
            .then(r => r.json())
            .then(res => {
                const items = Array.isArray(res) ? res : (res.data || []);
                if (items.length > 0) {
                    let html = '<option value="">-- Select Sub Category --</option>';
                    items.forEach(item => {
                        const isSel = (preselectSubId && String(preselectSubId) === String(item.id)) ? 'selected' : '';
                        html += `<option value="${item.id}" ${isSel}>${item.name}</option>`;
                    });
                    subcatSelect.innerHTML = html;
                    if (preselectSubId) {
                        subcatSelect.value = preselectSubId;
                        loadSubSubcatsForCard(subcatSelect, subsubcatSelect, preselectSubSubId);
                    }
                } else {
                    subcatSelect.innerHTML = '<option value="">-- No Subcategories --</option>';
                }
            })
            .catch(() => {
                subcatSelect.innerHTML = '<option value="">-- Error Loading --</option>';
            });
    }

    function loadSubSubcatsForCard(subcatSelect, subsubcatSelect, preselectSubSubId = null) {
        const subId = subcatSelect.value;
        subsubcatSelect.innerHTML = '<option value="">Loading...</option>';

        if (!subId) {
            subsubcatSelect.innerHTML = '<option value="">-- Select Sub Category First --</option>';
            return;
        }

        fetch('<?= \App\Core\Controller::url('/api/categories/subsubcategories?subcategory_id=') ?>' + encodeURIComponent(subId))
            .then(r => r.json())
            .then(res => {
                const items = Array.isArray(res) ? res : (res.data || []);
                if (items.length > 0) {
                    let html = '<option value="">-- Select Sub Sub Category --</option>';
                    items.forEach(item => {
                        const isSel = (preselectSubSubId && String(preselectSubSubId) === String(item.id)) ? 'selected' : '';
                        html += `<option value="${item.id}" ${isSel}>${item.name}</option>`;
                    });
                    subsubcatSelect.innerHTML = html;
                    if (preselectSubSubId) {
                        subsubcatSelect.value = preselectSubSubId;
                    }
                } else {
                    subsubcatSelect.innerHTML = '<option value="">-- No Sub-Subcategories --</option>';
                }
            })
            .catch(() => {
                subsubcatSelect.innerHTML = '<option value="">-- Error Loading --</option>';
            });
    }

    function updateHierarchyBadgeFromCats(catSelect, subcatSelect, subsubcatSelect, badgeEl, catalogSelect) {
        if (catalogSelect && catalogSelect.value) {
            const selOpt = catalogSelect.options[catalogSelect.selectedIndex];
            if (badgeEl && selOpt) {
                badgeEl.textContent = selOpt.text.trim();
                badgeEl.className = 'badge bg-primary text-white border-0 roll-catalog-badge';
            }
            return;
        }

        // Auto-match catalog from CATALOGS
        const subsubId = subsubcatSelect ? subsubcatSelect.value : '';
        const subcatId = subcatSelect ? subcatSelect.value : '';
        const catId = catSelect ? catSelect.value : '';

        if (catalogSelect && typeof CATALOGS !== 'undefined' && CATALOGS.length > 0) {
            let matched = null;
            if (subsubId) {
                matched = CATALOGS.find(c => String(c.sub_sub_category_id) === String(subsubId));
            }
            if (!matched && subcatId && catId) {
                matched = CATALOGS.find(c => String(c.subcategory_id) === String(subcatId) && String(c.category_id) === String(catId));
            }
            if (!matched && catId) {
                matched = CATALOGS.find(c => String(c.category_id) === String(catId) && (!c.subcategory_id || String(c.subcategory_id) === String(subcatId)));
            }
            if (matched) {
                catalogSelect.value = matched.id;
                if (badgeEl) {
                    badgeEl.textContent = `${matched.style_code ? '[' + matched.style_code + '] ' : ''}${matched.name}`;
                    badgeEl.className = 'badge bg-primary text-white border-0 roll-catalog-badge';
                }
                return;
            }
        }

        const c1 = catSelect.selectedIndex > 0 ? catSelect.options[catSelect.selectedIndex].text : '';
        const c2 = subcatSelect.selectedIndex > 0 ? subcatSelect.options[subcatSelect.selectedIndex].text : '';
        const c3 = subsubcatSelect.selectedIndex > 0 ? subsubcatSelect.options[subsubcatSelect.selectedIndex].text : '';
        const parts = [c1, c2, c3].filter(p => Boolean(p) && !p.startsWith('--'));
        if (parts.length > 0) {
            badgeEl.textContent = parts.join(' > ');
            badgeEl.className = 'badge bg-info-subtle text-info-emphasis border border-info-subtle roll-catalog-badge';
        } else {
            badgeEl.textContent = 'No Catalog Selected';
            badgeEl.className = 'badge bg-white text-secondary border roll-catalog-badge';
        }
    }

    function validateRollAllocation(input, remaining, msgEl) {
        const val = parseFloat(input.value);
        if (isNaN(val) || val <= 0) {
            input.classList.add('is-invalid');
            msgEl.textContent = 'Allocated quantity must be greater than zero.';
            msgEl.classList.remove('d-none');
            return false;
        } else if (val > (remaining + 0.001)) {
            input.classList.add('is-invalid');
            msgEl.textContent = `Exceeds available stock balance (max: ${remaining.toFixed(2)})!`;
            msgEl.classList.remove('d-none');
            return false;
        } else {
            input.classList.remove('is-invalid');
            msgEl.classList.add('d-none');
            return true;
        }
    }

    function renumberRollCards() {
        const cards = rollCardsContainer.querySelectorAll('.roll-card');
        cards.forEach((c, idx) => {
            const num = idx + 1;
            c.querySelector('.badge').textContent = `Roll #${num}`;
        });
    }

    function updateSummary() {
        const cards = rollCardsContainer.querySelectorAll('.roll-card');
        let totalRolls = 0;
        let totalQty = 0;
        let totalWeight = 0;
        let totalValue = 0;

        cards.forEach(c => {
            const rollSelect = c.querySelector('.select-roll-item');
            const qtyInput = c.querySelector('.input-allocated-qty');
            const weightInput = c.querySelector('.input-roll-weight');
            const priceInput = c.querySelector('.input-roll-price');

            if (rollSelect && rollSelect.value) {
                totalRolls++;
                const q = parseFloat(qtyInput?.value) || 0;
                const w = parseFloat(weightInput?.value) || 0;
                const p = parseFloat(priceInput?.value) || 0;

                totalQty += q;
                totalWeight += w;
                totalValue += p; // p is already the dynamically calculated roll price (qty * unit_rate)
            }
        });

        document.getElementById('summaryTotalRolls').textContent = totalRolls;
        document.getElementById('summaryTotalQty').textContent = `${totalQty.toFixed(2)}`;
        document.getElementById('summaryTotalWeight').textContent = `${totalWeight.toFixed(2)} kg`;
        document.getElementById('summaryTotalValue').textContent = `₹${totalValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

        if (totalRolls > 0) {
            rollValidationMsg.classList.add('d-none');
        }
    }

    // ==========================================
    // BARCODE / QR SCANNER ACTION
    // ==========================================
    function handleScanLookup() {
        const tag = scanRollInput.value.trim();
        if (!tag) return;

        scanErrorMsg.classList.add('d-none');

        fetch('<?= \App\Core\Controller::url('/api/programs/roll-lookup?tag=') ?>' + encodeURIComponent(tag))
            .then(r => r.json())
            .then(res => {
                if (res.success && res.roll) {
                    const roll = res.roll;
                    let existingEmptyCard = null;
                    const cards = rollCardsContainer.querySelectorAll('.roll-card');
                    cards.forEach(c => {
                        const sel = c.querySelector('.select-roll-item');
                        if (!sel.value && !existingEmptyCard) {
                            existingEmptyCard = c;
                        }
                    });

                    if (existingEmptyCard) {
                        const sel = existingEmptyCard.querySelector('.select-roll-item');
                        sel.value = roll.id;
                        sel.dispatchEvent(new Event('change'));
                        existingEmptyCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        addRollCard(roll.id);
                    }
                    scanRollInput.value = '';
                    scanRollInput.focus();
                } else {
                    scanErrorMsg.textContent = res.message || 'Roll piece tag not found in purchase stocks.';
                    scanErrorMsg.classList.remove('d-none');
                }
            })
            .catch(() => {
                scanErrorMsg.textContent = 'Network error looking up roll.';
                scanErrorMsg.classList.remove('d-none');
            });
    }

    document.getElementById('btnTriggerScan').addEventListener('click', handleScanLookup);
    scanRollInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleScanLookup();
        }
    });

    // ==========================================
    // FINAL FORM VALIDATION
    // ==========================================
    document.getElementById('formCreateProgram').addEventListener('submit', function(e) {
        let valid = true;

        const nameInput = document.getElementById('inputProgramName');
        if (!nameInput.value.trim() || nameInput.value.trim().length < 3) {
            nameInput.classList.add('is-invalid');
            valid = false;
        } else {
            nameInput.classList.remove('is-invalid');
        }

        const startDate = document.getElementById('inputStartDate').value;
        const targetDate = document.getElementById('inputTargetDate').value;
        if (new Date(targetDate) < new Date(startDate)) {
            document.getElementById('inputTargetDate').classList.add('is-invalid');
            valid = false;
        } else {
            document.getElementById('inputTargetDate').classList.remove('is-invalid');
        }

        const cards = rollCardsContainer.querySelectorAll('.roll-card');
        if (cards.length === 0) {
            rollValidationMsg.classList.remove('d-none');
            valid = false;
        }

        let hasValidRoll = false;
        cards.forEach(c => {
            const sel = c.querySelector('.select-roll-item');
            const qtyInput = c.querySelector('.input-allocated-qty');
            const exceedMsg = c.querySelector('.roll-exceed-msg');

            if (!sel.value) {
                sel.classList.add('is-invalid');
                valid = false;
            } else {
                sel.classList.remove('is-invalid');
                const rollId = sel.value;
                const stockInfo = getEffectiveRollStock(rollId, c);
                const rem = stockInfo.remainingForCard;
                if (!validateRollAllocation(qtyInput, rem, exceedMsg)) {
                    valid = false;
                } else {
                    hasValidRoll = true;
                }
            }
        });

        if (!hasValidRoll) {
            rollValidationMsg.classList.remove('d-none');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            e.stopPropagation();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
});
</script>
