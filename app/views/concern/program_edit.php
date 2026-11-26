<?php
// app/views/concern/program_edit.php - Edit Production Program with Multi-Roll Allocation & Out-of-Qty Tracking
?>
<div class="container-fluid px-0">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item text-muted">Production</li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog?tab=programs') ?>" class="text-decoration-none text-muted">Programs</a></li>
            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page">Edit Program</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2 rounded-3 bg-primary-subtle text-primary border border-primary-subtle shadow-xs">
                <i class="bi bi-pencil-square fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Edit Production Program</h4>
                <p class="text-secondary small mb-0">Update program parameters, adjust roll allocations with live out-of-qty tracking, and manage lifecycle status.</p>
            </div>
        </div>
        <div>
            <a href="<?= \App\Core\Controller::url('/production/catalog?tab=programs') ?>" class="btn btn-outline-secondary rounded-3 d-inline-flex align-items-center gap-2 shadow-sm fw-semibold">
                <i class="bi bi-arrow-left"></i> Back to Programs
            </a>
        </div>
    </div>

    <!-- Main Edit Form -->
    <form action="<?= \App\Core\Controller::url('/production/programs/update') ?>" method="POST" id="formEditProgram" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
        <input type="hidden" name="id" value="<?= (int)$program['id'] ?>">

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
                        <small class="text-secondary" style="font-size: 0.78rem;">Define the unique program title, code, and status.</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">Program Name <span class="text-danger">*</span></label>
                            <input type="text" name="program_name" id="inputProgramName" class="form-control" value="<?= htmlspecialchars($program['program_name'] ?? '') ?>" placeholder="E.G. SUMMER MATERNITY COLLECTION 2026" required autofocus>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Descriptive title for this manufacturing run.</small>
                            <div class="invalid-feedback">Program title is required (minimum 3 characters).</div>
                        </div>

                        <input type="hidden" name="status" value="<?= htmlspecialchars($program['status']) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Program Code / Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted font-monospace">#</span>
                                    <input type="text" name="program_no" id="inputProgramNo" class="form-control font-monospace fw-bold bg-light" value="<?= htmlspecialchars($program['program_no']) ?>" readonly required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Planner / Creator <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person-fill"></i></span>
                                    <input type="text" name="planner_name" class="form-control bg-light fw-semibold" value="<?= htmlspecialchars($program['planner_name'] ?? 'System Administrator') ?>" readonly required>
                                </div>
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
                            <small class="text-secondary" style="font-size: 0.78rem;">Assign fabric rolls and configure 3-tier catalog categories for each roll.</small>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <button type="button" class="btn btn-sm btn-primary rounded-3 fw-semibold shadow-xs" id="btnAddRollCard">
                                <i class="bi bi-plus-circle me-1"></i> Add Roll
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        
                        <!-- BARCODE SCANNER BAR -->
                        <div class="p-3 mb-4 rounded-3 border border-success-subtle bg-success-subtle bg-opacity-10">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-upc-scan text-success fs-5"></i>
                                    <strong class="text-dark small">Scan Fabric Roll Barcode / QR Tag</strong>
                                    <span class="badge bg-success text-white py-0.5 px-2" style="font-size: 0.7rem;">Auto-Add Ready</span>
                                </div>
                                <span class="small text-muted" style="font-size: 0.75rem;">Hardware Gun & Camera Scanner</span>
                            </div>
                            <div class="input-group">
                                <input type="text" id="scannerInput" class="form-control" placeholder="Scan roll QR or type piece tag (e.g. FB1126141) and press Enter to auto-add..." autocomplete="off">
                                <button type="button" class="btn btn-success px-3 fw-semibold" id="btnTriggerScan">
                                    <i class="bi bi-qr-code-scan me-1"></i> Scan / Add
                                </button>
                            </div>
                            <div id="scannerFeedback" class="small mt-1 text-danger d-none"></div>
                        </div>

                        <!-- CONTAINER FOR DYNAMIC ROLL CARDS -->
                        <div id="rollsContainer" class="d-flex flex-column gap-3">
                            <!-- Populated dynamically via JS from existingRolls -->
                        </div>

                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- RIGHT COLUMN: SECTION 3 & SUMMARY CARD     -->
            <!-- ========================================== -->
            <div class="col-lg-5">
                
                <!-- 3. PROGRAMMING TIMELINE & SCHEDULING -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-calendar-range-fill text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark">3. Programming Timeline & Scheduling</h6>
                        </div>
                        <small class="text-secondary" style="font-size: 0.78rem;">Specify the manufacturing start and completion target dates.</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">Programming Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="inputStartDate" class="form-control" value="<?= htmlspecialchars($program['start_date']) ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-dark mb-1">Target Completion / End Date <span class="text-danger">*</span></label>
                            <input type="date" name="target_date" id="inputTargetDate" class="form-control" value="<?= htmlspecialchars($program['target_date']) ?>" required>
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

        <!-- BOTTOM ACTIONS BAR -->
        <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded-3 shadow-sm border mt-2">
            <a href="<?= \App\Core\Controller::url('/production/catalog?tab=programs') ?>" class="btn btn-light border px-4 py-2 fw-semibold text-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" id="btnSaveProgram">
                <i class="bi bi-check2-circle fs-5"></i> Update Production Program
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/program_roll_picker_assets.php'; ?>
<script>
// Data from server
const AVAILABLE_ROLLS = <?= json_encode($availableRolls) ?>;
const CATEGORIES = <?= json_encode($categories) ?>;
const SUBCATEGORIES = <?= json_encode($subcategories ?? []) ?>;
const SUBSUBCATEGORIES = <?= json_encode($subSubcategories ?? []) ?>;
const CATALOGS = <?= json_encode($catalogs) ?>;
const EXISTING_ROLLS = <?= json_encode($existingRolls) ?>;

let rollCardCounter = 0;

// Dynamic effective balance calculation across all roll cards for a given roll
function getEffectiveRollStock(rollItemId, currentCardIndex = null) {
    const roll = AVAILABLE_ROLLS.find(r => String(r.id) === String(rollItemId));
    if (!roll) return { totalAvailable: 0, allocatedInOthers: 0, remainingForCard: 0, unit: 'pcs' };

    const totalAvailable = parseFloat(roll.remaining_qty || 0);
    const unit = roll.unit || 'kg';

    let allocatedInOthers = 0;
    const cards = document.querySelectorAll('.roll-card');
    cards.forEach(card => {
        const idx = card.getAttribute('data-index');
        if (currentCardIndex !== null && currentCardIndex !== undefined && String(idx) === String(currentCardIndex)) {
            return; // skip current card
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

function createRollCardHtml(index, rollData = null) {
    const cardId = `rollCard_${index}`;
    const selectedRollId = rollData ? rollData.purchase_bill_item_id : '';
    const allocatedQtyVal = rollData && rollData.allocated_qty !== undefined && rollData.allocated_qty !== '' ? Math.floor(parseFloat(rollData.allocated_qty)) : '';
    const rollWeightVal = rollData && rollData.roll_weight ? parseFloat(rollData.roll_weight) : '0.00';
    const rollPriceVal = rollData && rollData.roll_price ? parseFloat(rollData.roll_price) : '0.00';
    const targetPiecesVal = rollData ? parseInt(rollData.target_pieces) : 0;
    const selectedCatId = rollData ? rollData.category_id : '';
    const selectedSubcatId = rollData ? rollData.subcategory_id : '';
    const selectedSubSubId = rollData ? rollData.sub_sub_category_id : '';
    const selectedCatalogId = rollData ? rollData.catalog_id : '';

    const rollOptionsHtml = window.ProgramRollPicker.buildSelectOptionsHtml(AVAILABLE_ROLLS, selectedRollId);
    const selectedRollObject = selectedRollId
        ? (AVAILABLE_ROLLS.find(r => String(r.id) === String(selectedRollId)) || null)
        : null;

    let catOptionsHtml = '<option value="">-- Select Main Category --</option>';
    CATEGORIES.forEach(c => {
        const sel = (selectedCatId && String(c.id) === String(selectedCatId)) ? 'selected' : '';
        catOptionsHtml += `<option value="${c.id}" ${sel}>${c.name}</option>`;
    });

    let catalogOptionsHtml = '<option value="">-- Select Product Style (Optional) --</option>';
    CATALOGS.forEach(cat => {
        const sel = (selectedCatalogId && String(cat.id) === String(selectedCatalogId)) ? 'selected' : '';
        catalogOptionsHtml += `<option value="${cat.id}" ${sel}>${cat.style_code ? `[${cat.style_code}] ` : ''}${cat.name}</option>`;
    });

    let initialStock = '0 pcs';
    if (selectedRollObject) {
        const stockInfo = getEffectiveRollStock(selectedRollObject.id, index);
        const rem = stockInfo.remainingForCard;
        const u = selectedRollObject.unit || 'kg';
        if (rem > 0) {
            if (stockInfo.allocatedInOthers > 0) {
                initialStock = `${rem.toFixed(2)} ${u} (${stockInfo.allocatedInOthers.toFixed(2)} in other rolls)`;
            } else {
                initialStock = `${rem.toFixed(2)} ${u}`;
            }
        } else {
            initialStock = `0.00 ${u} (Fully Allocated)`;
        }
    }

    return `
    <div class="card border rounded-3 p-3 bg-white roll-card shadow-xs mb-3" id="${cardId}" data-index="${index}">
        <input type="hidden" name="rolls[${index}][program_roll_id]" class="input-program-roll-id" value="${rollData ? (rollData.program_roll_id || rollData.id || '') : ''}">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1 roll-index-badge">Roll #${index + 1}</span>
            <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2 btn-remove-roll" onclick="removeRollCard('${cardId}')" title="Remove this roll">
                <i class="bi bi-trash me-1"></i> Remove
            </button>
        </div>

        <!-- 1. Select Raw Material Roll * -->
        <div class="row g-2 mb-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-dark mb-1">Supplier Filter</label>
                <select class="form-select roll-supplier-filter-select">
                    <option value="">All Suppliers</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label small fw-semibold text-dark mb-1">Select Raw Material Roll <span class="text-danger">*</span></label>
                <select name="rolls[${index}][purchase_bill_item_id]" class="form-select select-roll-item d-none" id="rollSelect_${index}" onchange="handleRollSelectionChange(${index})" required>
                    ${rollOptionsHtml}
                </select>
                <div class="roll-picker-wrap">
                    <button type="button" class="roll-picker-btn" aria-expanded="false">
                        <div class="roll-picker-btn-main"></div>
                        <i class="bi bi-chevron-down text-muted flex-shrink-0 small"></i>
                    </button>
                    <div class="roll-picker-menu d-none">
                        <div class="roll-picker-search-bar">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0 roll-picker-search" placeholder="Search fabric, color, code, supplier, or bill #...">
                            </div>
                        </div>
                        <div class="roll-picker-list"></div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="roll-selected-summary d-none"></div>
            </div>
        </div>

        <!-- 2. Roll Qty, Roll Weight, Roll Price matching Image 1 -->
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-semibold text-dark mb-0">Roll Qty (<span class="roll-unit-label" id="unitLabel_${index}">${selectedRollObject ? selectedRollObject.unit : 'pcs'}</span>) <span class="text-danger">*</span></label>
                    <span class="roll-stock-badge text-muted small" id="stockBadge_${index}" style="font-size: 0.72rem;">Stock: ${initialStock}</span>
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-box-seam"></i></span>
                    <input type="number" step="1" min="1" name="rolls[${index}][allocated_qty]" id="allocatedQty_${index}" class="form-control input-allocated-qty" value="${allocatedQtyVal}" placeholder="Select Roll" oninput="handleRollAllocatedQtyInput(${index})" onkeydown="handleRollAllocatedQtyKeydown(event, ${index})" required>
                    <span class="input-group-text bg-light text-muted roll-unit-text" id="unitText_${index}">${selectedRollObject ? selectedRollObject.unit : 'pcs'}</span>
                </div>
                <div class="text-danger small mt-0.5 d-none qty-error-feedback" id="qtyError_${index}" style="font-size: 0.70rem;"></div>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold text-dark mb-1">Roll Weight (kg)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-speedometer2"></i></span>
                    <input type="number" step="0.01" min="0.00" name="rolls[${index}][roll_weight]" id="rollWeight_${index}" class="form-control input-roll-weight bg-light fw-semibold" value="${rollWeightVal}" readonly tabindex="-1" title="Calculated dynamically based on Roll Qty">
                    <span class="input-group-text bg-light text-muted">kg</span>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold text-dark mb-1">Roll Price (₹)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted">₹</span>
                    <input type="number" step="0.01" min="0.00" name="rolls[${index}][roll_price]" id="rollPrice_${index}" class="form-control input-roll-price font-monospace bg-light fw-bold" value="${rollPriceVal}" readonly tabindex="-1" title="Calculated dynamically based on Roll Qty">
                </div>
            </div>
        </div>

        <input type="hidden" name="rolls[${index}][target_pieces]" class="input-target-pieces" value="${targetPiecesVal}">

        <!-- 3. Catalog Hierarchy for this Roll matching Image 1 -->
        <div class="card border rounded-3 p-3 bg-light">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-1.5 small fw-bold text-primary">
                    <i class="bi bi-diagram-3"></i>
                    <span>Catalog Hierarchy for this Roll</span>
                </div>
                <span class="badge bg-white text-secondary border roll-catalog-badge" id="catalogBadge_${index}">No Catalog Selected</span>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Main Category</label>
                    <select name="rolls[${index}][category_id]" id="catSelect_${index}" class="form-select form-select-sm select-category" onchange="loadSubcategoriesForRoll(${index})">
                        ${catOptionsHtml}
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Sub Category</label>
                    <select name="rolls[${index}][subcategory_id]" id="subcatSelect_${index}" class="form-select form-select-sm select-subcategory" onchange="loadSubSubcategoriesForRoll(${index})">
                        <option value="">-- Select Category First --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Sub Sub Category</label>
                    <select name="rolls[${index}][sub_sub_category_id]" id="subsubSelect_${index}" class="form-select form-select-sm select-subsubcategory" onchange="updateHierarchyBadgeFromCats(${index})">
                        <option value="">-- Select Sub Category First --</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="form-label small text-muted mb-1">Product Style / Catalog Style</label>
                <select name="rolls[${index}][catalog_id]" id="catalogSelect_${index}" class="form-select form-select-sm select-catalog" onchange="handleCatalogSelectChange(${index})">
                    ${catalogOptionsHtml}
                </select>
            </div>
        </div>
    </div>
    `;
}

function addRollCard(rollData = null) {
    const container = document.getElementById('rollsContainer');
    const index = rollCardCounter++;
    container.insertAdjacentHTML('beforeend', createRollCardHtml(index, rollData));
    const cardEl = document.getElementById(`rollCard_${index}`);
    if (cardEl && window.ProgramRollPicker) {
        window.ProgramRollPicker.attach(cardEl, AVAILABLE_ROLLS, id => getEffectiveRollStock(id, index));
    }
    
    // If existing subcategory/sub-sub were selected, populate them
    if (rollData && rollData.category_id) {
        loadSubcategoriesForRoll(index, rollData.subcategory_id, rollData.sub_sub_category_id);
    }
    
    // Auto-update catalog badge if roll had catalog or category hierarchy
    const catBadge = document.getElementById(`catalogBadge_${index}`);
    if (rollData && (rollData.catalog_name || rollData.catalog_style_code)) {
        if (catBadge) {
            catBadge.textContent = `${rollData.catalog_style_code ? '[' + rollData.catalog_style_code + '] ' : ''}${rollData.catalog_name}`;
            catBadge.className = 'badge bg-primary text-white border-0 roll-catalog-badge';
        }
    } else if (rollData && (rollData.category_name || rollData.subcategory_name || rollData.subsub_name)) {
        const parts = [rollData.category_name, rollData.subcategory_name, rollData.subsub_name].filter(Boolean);
        if (catBadge && parts.length > 0) {
            catBadge.textContent = parts.join(' > ');
            catBadge.className = 'badge bg-info-subtle text-info-emphasis border border-info-subtle roll-catalog-badge';
        }
    } else if (rollData && rollData.catalog_id) {
        const catSelect = document.getElementById(`catalogSelect_${index}`);
        if (catBadge && catSelect && catSelect.selectedIndex > 0) {
            catBadge.textContent = catSelect.options[catSelect.selectedIndex].text;
            catBadge.className = 'badge bg-primary text-white border-0 roll-catalog-badge';
        }
    }
    
    if (rollData && rollData.purchase_bill_item_id) {
        calculateEditCardWeightAndPrice(index);
    }
    
    recalcSummary();
    renumberCards();
    updateAllRollCardsStock();
}

function calculateEditCardWeightAndPrice(index) {
    const select = document.getElementById(`rollSelect_${index}`);
    const input = document.getElementById(`allocatedQty_${index}`);
    const weightInput = document.getElementById(`rollWeight_${index}`);
    const priceInput = document.getElementById(`rollPrice_${index}`);

    if (!select || !weightInput || !priceInput) return;
    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        weightInput.value = '0.00';
        priceInput.value = '0.00';
        return;
    }

    const qty = parseInt(input ? input.value : 0, 10) || 0;
    const unit = (selectedOption.dataset.unit || 'kg').toLowerCase();
    const totalQty = parseFloat(selectedOption.dataset.total || 0);
    const totalWeight = parseFloat(selectedOption.dataset.weight || 0);
    const unitRate = parseFloat(selectedOption.dataset.price || 0);

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

function removeRollCard(cardId) {
    const el = document.getElementById(cardId);
    if (!el) return;
    const cards = document.querySelectorAll('.roll-card');
    if (cards.length <= 1) {
        alert('A production program must have at least one assigned raw material roll.');
        return;
    }
    el.remove();
    renumberCards();
    updateAllRollCardsStock();
    recalcSummary();
}

function renumberCards() {
    const cards = document.querySelectorAll('.roll-card');
    cards.forEach((card, idx) => {
        const badge = card.querySelector('.roll-index-badge');
        if (badge) badge.textContent = `Roll #${idx + 1}`;
    });
}

function handleRollSelectionChange(index) {
    const select = document.getElementById(`rollSelect_${index}`);
    const selectedOption = select ? select.options[select.selectedIndex] : null;
    const stockBadge = document.getElementById(`stockBadge_${index}`);
    const unitLabel = document.getElementById(`unitLabel_${index}`);
    const unitText = document.getElementById(`unitText_${index}`);
    const input = document.getElementById(`allocatedQty_${index}`);
    const weightInput = document.getElementById(`rollWeight_${index}`);
    const priceInput = document.getElementById(`rollPrice_${index}`);
    const catalogBadge = document.getElementById(`catalogBadge_${index}`);
    const catSelect = document.getElementById(`catSelect_${index}`);
    const subcatSelect = document.getElementById(`subcatSelect_${index}`);
    const subsubSelect = document.getElementById(`subsubSelect_${index}`);
    const catalogSelect = document.getElementById(`catalogSelect_${index}`);
    const errorDiv = document.getElementById(`qtyError_${index}`);

    if (!selectedOption || !selectedOption.value) {
        if (stockBadge) stockBadge.textContent = 'Stock: 0 pcs';
        if (unitLabel) unitLabel.textContent = 'pcs';
        if (unitText) unitText.textContent = 'pcs';
        if (input) {
            input.value = '';
            input.removeAttribute('max');
            input.classList.remove('is-invalid');
        }
        if (weightInput) weightInput.value = '0.00';
        if (priceInput) priceInput.value = '0.00';
        if (catalogBadge) {
            catalogBadge.textContent = 'No Catalog Selected';
            catalogBadge.className = 'badge bg-white text-secondary border roll-catalog-badge';
        }
        if (errorDiv) errorDiv.classList.add('d-none');
        if (window.ProgramRollPicker) {
            const cardEl = document.getElementById(`rollCard_${index}`);
            if (cardEl) window.ProgramRollPicker.refreshCard(cardEl, AVAILABLE_ROLLS, id => getEffectiveRollStock(id, index));
        }
        updateAllRollCardsStock(index);
        recalcSummary();
        return;
    }

    const rollItemId = selectedOption.value;
    const stockInfo = getEffectiveRollStock(rollItemId, index);
    const unit = stockInfo.unit || selectedOption.dataset.unit || 'kg';
    const effectiveStock = stockInfo.remainingForCard;

    if (unitLabel) unitLabel.textContent = unit;
    if (unitText) unitText.textContent = unit;

    // 1. Update Stock Badge with dynamic remaining balance
    if (stockBadge) {
        if (effectiveStock > 0) {
            if (stockInfo.allocatedInOthers > 0) {
                stockBadge.innerHTML = `Stock: <strong class="text-success">${effectiveStock.toFixed(2)} ${unit}</strong> <span class="text-muted small">(${stockInfo.allocatedInOthers.toFixed(2)} in other rolls)</span>`;
            } else {
                stockBadge.innerHTML = `Stock: <strong class="text-success">${effectiveStock.toFixed(2)} ${unit}</strong>`;
            }
        } else {
            stockBadge.innerHTML = `Stock: <strong class="text-danger">0.00 ${unit} (Fully Allocated)</strong>`;
        }
    }

    // 2. Set Allocated Qty to available balance if empty, or cap to max (Strict Integer only)
    if (input) {
        const intEffectiveStock = Math.floor(effectiveStock);
        if (!input.value || parseInt(input.value, 10) <= 0) {
            input.value = intEffectiveStock > 0 ? intEffectiveStock : (effectiveStock > 0 ? 1 : '0');
        }
        input.setAttribute('max', intEffectiveStock > 0 ? intEffectiveStock : '1');
        input.setAttribute('step', '1');
        input.setAttribute('min', '1');
    }

    calculateEditCardWeightAndPrice(index);

    // Catalog auto-sync
    const catId = selectedOption.dataset.categoryId;
    const subcatId = selectedOption.dataset.subcategoryId;
    const subsubcatId = selectedOption.dataset.subsubcategoryId;
    const catalogId = selectedOption.dataset.catalogId;
    const hierarchy = selectedOption.dataset.hierarchy;
    const catCode = selectedOption.dataset.catalogCode;
    const catName = selectedOption.dataset.catalogName;

    if (catalogId && catalogSelect) {
        catalogSelect.value = catalogId;
        if (catalogBadge) {
            catalogBadge.textContent = catCode ? `[${catCode}] ${catName}` : (catName || 'Mapped Catalog');
            catalogBadge.className = 'badge bg-primary text-white border-0 roll-catalog-badge';
        }
    } else if (hierarchy && catalogBadge) {
        catalogBadge.textContent = hierarchy;
        catalogBadge.className = 'badge bg-info-subtle text-info-emphasis border border-info-subtle roll-catalog-badge';
    } else if (catalogBadge) {
        catalogBadge.textContent = 'No Catalog Selected';
        catalogBadge.className = 'badge bg-white text-secondary border roll-catalog-badge';
    }

    if (catId && catSelect) {
        catSelect.value = catId;
        loadSubcategoriesForRoll(index, subcatId, subsubcatId);
    }

    validateRollAllocatedQty(index);
    updateAllRollCardsStock(index);
    recalcSummary();
}

function handleRollAllocatedQtyKeydown(event, index) {
    if (event.key === '.' || event.key === ',' || event.key === 'e' || event.key === 'E' || event.key === '+' || event.key === '-') {
        event.preventDefault();
    }
}

function handleRollAllocatedQtyInput(index) {
    const input = document.getElementById(`allocatedQty_${index}`);
    if (input) {
        let val = input.value;
        if (val.includes('.') || val.includes(',')) {
            val = Math.floor(parseFloat(val)) || '';
            input.value = val;
        }
        if (val !== '' && parseInt(val, 10) < 1) {
            input.value = 1;
        }
    }
    calculateEditCardWeightAndPrice(index);
    validateRollAllocatedQty(index);
    updateAllRollCardsStock(index);
    recalcSummary();
}

function validateRollAllocatedQty(index) {
    const input = document.getElementById(`allocatedQty_${index}`);
    const select = document.getElementById(`rollSelect_${index}`);
    const errorDiv = document.getElementById(`qtyError_${index}`);

    if (!input || !select) return false;

    calculateEditCardWeightAndPrice(index);

    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) return false;

    const rollItemId = selectedOption.value;
    const stockInfo = getEffectiveRollStock(rollItemId, index);
    const maxAllowed = stockInfo.remainingForCard;
    const unit = stockInfo.unit || 'kg';
    const val = parseFloat(input.value);

    if (isNaN(val) || val <= 0) {
        input.classList.add('is-invalid');
        if (errorDiv) {
            errorDiv.classList.remove('d-none');
            errorDiv.textContent = 'Allocated quantity must be greater than zero.';
        }
        return false;
    } else if (val > (maxAllowed + 0.001)) {
        input.classList.add('is-invalid');
        if (errorDiv) {
            errorDiv.classList.remove('d-none');
            errorDiv.textContent = `Allocated qty (${val.toFixed(2)} ${unit}) exceeds available stock balance (${maxAllowed.toFixed(2)} ${unit})!`;
        }
        return false;
    } else {
        input.classList.remove('is-invalid');
        if (errorDiv) errorDiv.classList.add('d-none');
        return true;
    }
}

function updateAllRollCardsStock(skipIndex = null) {
    const cards = document.querySelectorAll('.roll-card');
    cards.forEach(card => {
        const idx = card.getAttribute('data-index');
        const select = card.querySelector('.select-roll-item');
        const input = card.querySelector('.input-allocated-qty');
        const stockBadge = card.querySelector('.roll-stock-badge');
        const errorDiv = card.querySelector('.qty-error-feedback');

        if (!select || !select.value) return;

        const rollItemId = select.value;
        const stockInfo = getEffectiveRollStock(rollItemId, idx);
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

        if (skipIndex === null || String(idx) !== String(skipIndex)) {
            if (input && input.value !== '') {
                const val = parseFloat(input.value);
                if (!isNaN(val) && val > (rem + 0.001)) {
                    input.classList.add('is-invalid');
                    if (errorDiv) {
                        errorDiv.classList.remove('d-none');
                        errorDiv.textContent = `Allocated qty (${val.toFixed(2)} ${unit}) exceeds available balance (${rem.toFixed(2)} ${unit})!`;
                    }
                } else if (!isNaN(val) && val > 0) {
                    input.classList.remove('is-invalid');
                    if (errorDiv) errorDiv.classList.add('d-none');
                }
            }
        }
    });

    // Update option labels and custom picker summary across all roll cards
    cards.forEach(card => {
        const idx = card.getAttribute('data-index');
        const select = card.querySelector('.select-roll-item');
        if (select) {
            Array.from(select.options).forEach(opt => {
                if (!opt.value) return;
                const optRollId = opt.value;
                const baseRoll = AVAILABLE_ROLLS.find(r => String(r.id) === String(optRollId));
                if (!baseRoll) return;

                const info = getEffectiveRollStock(optRollId, idx);
                opt.dataset.remaining = info.remainingForCard;

                const specTitle = `${(baseRoll.design_name || baseRoll.pattern || baseRoll.item_name || 'Fabric Roll').toUpperCase()} • ${(baseRoll.color || 'STANDARD').toUpperCase()}`;
                const supInfo = baseRoll.supplier_display ? ` • ${baseRoll.supplier_display}` : '';
                const billInfo = baseRoll.bill_no ? ` • Bill #${baseRoll.bill_no}` : '';
                const stockStr = info.remainingForCard <= 0
                    ? ` • OUT OF STOCK (0.00 ${baseRoll.unit || 'kg'})`
                    : ` • Stock: ${info.remainingForCard.toFixed(2)} ${baseRoll.unit || 'kg'}`;

                opt.text = `${specTitle}${supInfo}${billInfo}${stockStr}`;
            });
        }
        if (window.ProgramRollPicker) {
            window.ProgramRollPicker.refreshCard(card, AVAILABLE_ROLLS, id => getEffectiveRollStock(id, idx));
        }
    });
}

function recalcSummary() {
    let totalRolls = 0;
    let totalQty = 0;
    let totalWeight = 0;
    let totalValue = 0;

    const cards = document.querySelectorAll('.roll-card');
    cards.forEach(card => {
        totalRolls++;
        const qtyInput = card.querySelector('.input-allocated-qty');
        const weightInput = card.querySelector('.input-roll-weight');
        const priceInput = card.querySelector('.input-roll-price');

        if (qtyInput) totalQty += parseFloat(qtyInput.value) || 0;
        if (weightInput) totalWeight += parseFloat(weightInput.value) || 0;
        if (priceInput) totalValue += parseFloat(priceInput.value) || 0;
    });

    const elRolls = document.getElementById('summaryTotalRolls');
    const elQty = document.getElementById('summaryTotalQty');
    const elWeight = document.getElementById('summaryTotalWeight');
    const elValue = document.getElementById('summaryTotalValue');

    if (elRolls) elRolls.textContent = totalRolls;
    if (elQty) elQty.textContent = `${totalQty.toFixed(2)} units`;
    if (elWeight) elWeight.textContent = `${totalWeight.toFixed(2)} kg`;
    if (elValue) elValue.textContent = `₹${totalValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

function loadSubcategoriesForRoll(index, preselectedSubId = null, preselectedSubSubId = null) {
    const catSelect = document.getElementById(`catSelect_${index}`);
    const subSelect = document.getElementById(`subcatSelect_${index}`);
    const subSubSelect = document.getElementById(`subsubSelect_${index}`);
    const catId = catSelect.value;

    subSelect.innerHTML = '<option value="">-- Subcategory --</option>';
    subSubSelect.innerHTML = '<option value="">-- Sub-Subcategory --</option>';

    if (!catId) {
        updateHierarchyBadgeFromCats(index);
        return;
    }

    fetch(`<?= \App\Core\Controller::url('/api/categories/subcategories?category_id=') ?>${catId}`)
        .then(r => r.json())
        .then(data => {
            const items = Array.isArray(data) ? data : (data.data || []);
            if (items.length > 0) {
                items.forEach(item => {
                    const sel = (preselectedSubId && String(item.id) === String(preselectedSubId)) ? 'selected' : '';
                    subSelect.innerHTML += `<option value="${item.id}" ${sel}>${item.name}</option>`;
                });
                if (preselectedSubId) {
                    loadSubSubcategoriesForRoll(index, preselectedSubSubId);
                } else {
                    updateHierarchyBadgeFromCats(index);
                }
            } else {
                updateHierarchyBadgeFromCats(index);
            }
        });
}

function loadSubSubcategoriesForRoll(index, preselectedSubSubId = null) {
    const subSelect = document.getElementById(`subcatSelect_${index}`);
    const subSubSelect = document.getElementById(`subsubSelect_${index}`);
    const subId = subSelect.value;

    subSubSelect.innerHTML = '<option value="">-- Sub-Subcategory --</option>';
    if (!subId) {
        updateHierarchyBadgeFromCats(index);
        return;
    }

    fetch(`<?= \App\Core\Controller::url('/api/categories/subsubcategories?subcategory_id=') ?>${subId}`)
        .then(r => r.json())
        .then(data => {
            const items = Array.isArray(data) ? data : (data.data || []);
            if (items.length > 0) {
                items.forEach(item => {
                    const sel = (preselectedSubSubId && String(item.id) === String(preselectedSubSubId)) ? 'selected' : '';
                    subSubSelect.innerHTML += `<option value="${item.id}" ${sel}>${item.name}</option>`;
                });
            }
            updateHierarchyBadgeFromCats(index);
        });
}

// Scanner handling
document.getElementById('btnTriggerScan').addEventListener('click', () => {
    const input = document.getElementById('scannerInput');
    const tag = input.value.trim();
    if (!tag) return;

    const feedback = document.getElementById('scannerFeedback');
    feedback.classList.add('d-none');

    fetch(`<?= \App\Core\Controller::url('/api/programs/roll-lookup?tag=') ?>${encodeURIComponent(tag)}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                feedback.textContent = res.message || 'Roll piece tag not found.';
                feedback.classList.remove('d-none');
                return;
            }
            addRollCard({
                purchase_bill_item_id: res.roll.id,
                allocated_qty: res.roll.remaining_qty,
                target_pieces: 0,
                roll_piece_tag: res.roll.item_code
            });
            input.value = '';
            input.focus();
        })
        .catch(err => {
            feedback.textContent = 'Network error looking up roll.';
            feedback.classList.remove('d-none');
        });
});

document.getElementById('scannerInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('btnTriggerScan').click();
    }
});

document.getElementById('btnAddRollCard').addEventListener('click', () => {
    addRollCard();
});

// Form submission validation
const formEdit = document.getElementById('formEditProgram');
if (formEdit) {
    formEdit.addEventListener('submit', (e) => {
        const cards = document.querySelectorAll('.roll-card');
        if (cards.length === 0) {
            e.preventDefault();
            alert('Please assign at least one raw material fabric roll.');
            return;
        }

        let isValid = true;
        let firstInvalid = null;

        cards.forEach(card => {
            const idx = card.getAttribute('data-index');
            const sel = card.querySelector('.select-roll-item');
            const pickerBtn = card.querySelector('.roll-picker-btn');
            const qtyInp = card.querySelector('.input-allocated-qty');

            if (!sel || !sel.value) {
                isValid = false;
                if (sel) sel.classList.add('is-invalid');
                if (pickerBtn) pickerBtn.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = pickerBtn || sel;
            } else {
                if (sel) sel.classList.remove('is-invalid');
                if (pickerBtn) pickerBtn.classList.remove('is-invalid');
            }

            if (!validateRollAllocatedQty(idx)) {
                isValid = false;
                if (!firstInvalid) firstInvalid = qtyInp;
            }
        });

        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
}

// Initialize existing rolls from server
document.addEventListener('DOMContentLoaded', () => {
    if (EXISTING_ROLLS && EXISTING_ROLLS.length > 0) {
        EXISTING_ROLLS.forEach(r => {
            addRollCard(r);
        });
    } else {
        addRollCard();
    }
    updateAllRollCardsStock();
});
</script>
