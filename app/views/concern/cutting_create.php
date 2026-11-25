<?php
/**
 * View: New Cutting Batch
 * Location: inventory_mekha/app/views/concern/cutting_create.php
 * Matches Image 1 & Image 2 (Cutting Batch Details, Fabric Wastage & Cost Analytics, Dynamic Variant Breakdown)
 */
?>
<div class="container-fluid px-4 py-3">

    <!-- BREADCRUMBS MATCHING IMAGE 1 -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="text-decoration-none text-muted">Cutting</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">New Cutting Batch</li>
        </ol>
    </nav>

    <form action="<?= \App\Core\Controller::url('/production/cutting/store') ?>" method="POST" id="formCreateCutting">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="batch_no" value="<?= htmlspecialchars($batchNo) ?>">

        <div class="row g-4">
            <!-- ========================================== -->
            <!-- LEFT COLUMN: 1. DETAILS & 2. WASTAGE/COSTS -->
            <!-- ========================================== -->
            <div class="col-lg-6">

                <!-- 1. CUTTING BATCH DETAILS (MATCHING IMAGE 1) -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-card-checklist text-primary fs-5"></i>
                            1. Cutting Batch Details
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        
                        <!-- Cutting Name * -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Cutting Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="cutting_name" id="cuttingName" class="form-control" 
                                   placeholder="E.G. SUMMER DRESS CUTTING LOT A" required>
                            <small class="text-muted" style="font-size: 0.75rem;">Identifies this specific cutting batch in production.</small>
                        </div>

                        <!-- Batch Number & Status Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Batch Number</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-upc-scan"></i></span>
                                    <input type="text" class="form-control bg-light font-monospace fw-bold text-dark" 
                                           value="<?= htmlspecialchars($batchNo) ?>" readonly tabindex="-1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="in_progress" selected>In Progress</option>
                                    <option value="planned">Planned</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Select Production Program * -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Select Production Program <span class="text-danger">*</span>
                            </label>
                            <select name="program_id" id="programSelect" class="form-select" required>
                                <option value="">-- Choose Production Program --</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?= $prog['id'] ?>" 
                                            data-planned="<?= $prog['planned_qty'] ?>"
                                            data-rolls="<?= $prog['rolls_count'] ?>">
                                        <?= htmlspecialchars($prog['program_no']) ?> • <?= htmlspecialchars($prog['program_name']) ?><?php if (empty($selectedCid) && !empty($prog['concern_name'])): ?> • <?= htmlspecialchars($prog['concern_name']) ?><?php endif; ?> (<?= $prog['rolls_count'] ?> Uncut Roll<?= $prog['rolls_count'] > 1 ? 's' : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="selectedProgramCatalogInfo" class="mt-2 d-none">
                                <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                    <div class="small">
                                        <span class="text-muted"><i class="bi bi-tag-fill text-primary me-1"></i> Catalog:</span>
                                        <strong id="selectedProgCatalogName" class="text-primary ms-1"></strong>
                                    </div>
                                    <span id="selectedProgRollsCount" class="badge bg-secondary text-white"></span>
                                </div>
                            </div>

                            <?php if (empty($programs)): ?>
                                <div class="alert alert-warning py-2 px-3 small mt-2 mb-0 rounded-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-6"></i>
                                    <span>All active production programs have already been cut. <a href="<?= \App\Core\Controller::url('/production/programs/create') ?>" class="alert-link">Create a new program</a>.</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Cutting Master / Operator & Add Master Modal trigger -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold text-dark mb-0">Cutting Master / Operator</label>
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none fw-semibold" 
                                        data-bs-toggle="modal" data-bs-target="#modalAddCuttingMaster">
                                    <i class="bi bi-plus-circle me-1"></i>+ Add Cutting Master
                                </button>
                            </div>
                            <select name="cutting_master_id" id="cuttingMasterSelect" class="form-select">
                                <option value="">Select Cutting Master</option>
                                <?php foreach ($cuttingMasters as $cm): ?>
                                    <option value="<?= $cm['id'] ?>">
                                        <?= htmlspecialchars($cm['name']) ?><?= $cm['phone'] ? ' (' . htmlspecialchars($cm['phone']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Notes & Remarks -->
                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-dark mb-1">Notes & Remarks</label>
                            <textarea name="notes" class="form-control" rows="2" 
                                      placeholder="Fabric lot notes, cutting instructions, etc..."></textarea>
                        </div>

                    </div>
                </div>

                <!-- 2. FABRIC WASTAGE & COST ANALYTICS (MATCHING IMAGE 2) -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <span class="badge rounded-circle bg-warning-subtle text-warning p-1">
                                    <i class="bi bi-speedometer2 fs-6"></i>
                                </span>
                                2. Fabric Wastage & Cost Analytics
                            </h6>
                            <small class="text-muted" style="font-size: 0.75rem;">Calculate multi-roll fabric wastage, rate per kg & exact wastage costs</small>
                        </div>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1.5 fs-7 fw-bold" id="badgeFabricRateTop">
                            ₹0.00 / kg
                        </span>
                    </div>
                    <div class="card-body p-4">

                        <!-- Total Roll Weight (kg) & Total Roll Price (₹) -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Total Roll Weight (kg)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" class="form-control bg-light fw-bold text-dark" 
                                           id="inputTotalRollWeight" value="0.00" readonly tabindex="-1">
                                    <span class="input-group-text bg-light text-muted">kg</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Total Roll Price (₹)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted">₹</span>
                                    <input type="number" step="0.01" class="form-control bg-light fw-bold font-monospace text-dark" 
                                           id="inputTotalRollPrice" value="0.00" readonly tabindex="-1">
                                </div>
                            </div>
                        </div>

                        <!-- Fabric Roll Wastage Breakdown Title & Badge -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold text-danger d-flex align-items-center gap-1.5">
                                <i class="bi bi-trash3 text-danger"></i> Fabric Roll Wastage Breakdown
                            </span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" id="badgeWastageCostTop">
                                Wastage Cost: ₹0.00
                            </span>
                        </div>

                        <!-- Prominent Red Wastage Input Card matching Image 2 -->
                        <div class="card bg-danger-subtle border border-danger-subtle rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-danger mb-0">Total Wastage Fabric Weight (kg)</label>
                                <span class="small fw-bold text-danger" id="spanWastageTotalCost">Total Cost: ₹0.00</span>
                            </div>
                            <div class="input-group input-group-lg my-1">
                                <input type="number" step="1" min="0" class="form-control fw-bolder text-danger fs-3 bg-white border-danger" 
                                       id="inputWastageWeight" name="wastage_qty" value="0" placeholder="0" required>
                                <span class="input-group-text bg-danger text-white fw-bold fs-5">kg</span>
                            </div>
                            <small class="text-muted" style="font-size: 0.75rem;">Total end cuts, scraps & defective cloth remaining across all selected rolls.</small>
                            <small class="text-danger small d-none mt-1 fw-semibold" id="wastageExceedMsg">
                                <i class="bi bi-exclamation-circle me-1"></i> Wastage weight cannot exceed total assigned roll weight!
                            </small>
                        </div>

                        <!-- 6-Grid Stats Cards matching Image 2 -->
                        <div class="row g-2 mb-4">
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Fabric Rate</small>
                                    <span class="fw-bold fs-6 text-dark" id="statFabricRate">₹0.00 / kg</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Wastage Cost</small>
                                    <span class="fw-bold fs-6 text-danger" id="statWastageCost">₹0.00</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Wastage %</small>
                                    <span class="fw-bold fs-6 text-warning" id="statWastagePct">0.0%</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Used Weight</small>
                                    <span class="fw-bold fs-6 text-primary" id="statUsedWeight">0.00 kg</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Total Cut Qty</small>
                                    <span class="fw-bold fs-6 text-success" id="statTotalCutQty">0 pcs</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Fabric Cost / Pc</small>
                                    <span class="fw-bold fs-6 text-info" id="statFabricCostPerPc">₹0.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Action Buttons matching Image 2 -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="btn btn-light border px-4 py-2 fw-semibold text-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" id="btnCreateBatch">
                                <i class="bi bi-check2-circle fs-5"></i> Create Cutting Batch
                            </button>
                        </div>

                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- RIGHT COLUMN: 3. DYNAMIC VARIANT BREAKDOWN -->
            <!-- ========================================== -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 d-flex flex-column">
                    
                    <!-- Header matching Image 1 -->
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <span class="badge rounded-circle bg-primary-subtle text-primary p-1">
                                    <i class="bi bi-grid-3x3-gap-fill fs-6"></i>
                                </span>
                                3. Dynamic Variant Cutting Breakdown
                            </h6>
                            <small class="text-muted" style="font-size: 0.75rem;">Variants dynamically generated for each selected fabric roll based on its specific catalog hierarchy</small>
                        </div>
                        <span class="badge bg-success px-3 py-1.5 fs-6 fw-bold shadow-sm" id="badgeTotalCutPcs">
                            0 Cut pcs
                        </span>
                    </div>

                    <!-- SELECT ROLL sub-bar matching Image 1 -->
                    <div class="px-3 py-2 bg-light border-bottom d-none" id="rollSelectionBar">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small fw-bold text-muted text-uppercase" style="font-size: 0.70rem; letter-spacing: 0.5px;">
                                <i class="bi bi-layers me-1"></i> SELECT ROLL:
                            </span>
                            <div class="d-flex gap-2 flex-wrap" id="rollTabsContainer">
                                <!-- Generated Dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- EMPTY STATE MATCHING IMAGE 1 -->
                    <div id="noProgramState" class="card-body d-flex flex-column justify-content-center align-items-center text-center p-5 flex-grow-1">
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mb-3 text-secondary border shadow-2xs" 
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-arrow-left-circle fs-1 text-primary"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">No Production Program Selected</h5>
                        <p class="text-muted small mb-0" style="max-width: 420px; line-height: 1.5;">
                            Please choose a Production Program and select Fabric Roll(s) with their specific Catalog Hierarchy from the left to dynamically generate cutting variants.
                        </p>
                    </div>

                    <!-- ACTIVE STATE: DYNAMIC ROLL VARIANT TABLES -->
                    <div id="activeProgramContainer" class="card-body p-3 flex-grow-1 d-none">
                        <div id="rollVariantPanels">
                            <!-- Populated dynamically per roll -->
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </form>
</div>

<!-- ========================================== -->
<!-- MODAL: QUICK ADD CUTTING MASTER / OPERATOR -->
<!-- ========================================== -->
<div class="modal fade" id="modalAddCuttingMaster" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-person-plus text-primary"></i> Add Cutting Master / Operator
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-dark mb-1">Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="newMasterName" class="form-control" placeholder="e.g. Ramesh Kumar" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-dark mb-1">Phone / Mobile</label>
                    <input type="tel" id="newMasterPhone" class="form-control" placeholder="10-digit mobile number" maxlength="10">
                    <div id="newMasterPhoneError" class="text-danger small mt-1 d-none" style="font-size: 0.75rem;"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-semibold text-dark mb-1">Employee / Operator Code</label>
                    <input type="text" id="newMasterCode" class="form-control" placeholder="e.g. OP-012">
                </div>
                <div id="masterErrorMsg" class="alert alert-danger py-2 px-3 small mt-3 mb-0 d-none"></div>
            </div>
            <div class="modal-footer border-top bg-light py-2">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary px-3 fw-semibold" id="btnSaveCuttingMaster">
                    <i class="bi bi-check2 me-1"></i> Save Master
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const programSelect = document.getElementById('programSelect');
    const noProgramState = document.getElementById('noProgramState');
    const activeProgramContainer = document.getElementById('activeProgramContainer');
    const rollSelectionBar = document.getElementById('rollSelectionBar');
    const rollTabsContainer = document.getElementById('rollTabsContainer');
    const rollVariantPanels = document.getElementById('rollVariantPanels');

    const inputTotalRollWeight = document.getElementById('inputTotalRollWeight');
    const inputTotalRollPrice = document.getElementById('inputTotalRollPrice');
    const inputWastageWeight = document.getElementById('inputWastageWeight');
    const wastageExceedMsg = document.getElementById('wastageExceedMsg');

    const badgeFabricRateTop = document.getElementById('badgeFabricRateTop');
    const badgeWastageCostTop = document.getElementById('badgeWastageCostTop');
    const spanWastageTotalCost = document.getElementById('spanWastageTotalCost');

    const statFabricRate = document.getElementById('statFabricRate');
    const statWastageCost = document.getElementById('statWastageCost');
    const statWastagePct = document.getElementById('statWastagePct');
    const statUsedWeight = document.getElementById('statUsedWeight');
    const statTotalCutQty = document.getElementById('statTotalCutQty');
    const statFabricCostPerPc = document.getElementById('statFabricCostPerPc');
    const badgeTotalCutPcs = document.getElementById('badgeTotalCutPcs');

    let currentProgramData = null;
    let activeRollId = null;

    // Handle program select change -> fetch rolls & dynamic variants
    programSelect.addEventListener('change', function() {
        const progId = this.value;
        if (!progId) {
            resetToEmptyState();
            return;
        }

        fetch(`<?= \App\Core\Controller::url('/api/cutting/program-details?id=') ?>${progId}`, {
            headers: { 'Accept': 'application/json' }
        })
            .then(async res => {
                let data;
                try {
                    data = await res.json();
                } catch (error) {
                    throw new Error('The server returned an invalid response while loading program details.');
                }
                if (!res.ok) {
                    throw new Error(data.message || `Unable to load program details (HTTP ${res.status}).`);
                }
                return data;
            })
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Failed to load program details.');
                    resetToEmptyState();
                    return;
                }

                currentProgramData = data;

                // Update program catalog info box
                const infoBox = document.getElementById('selectedProgramCatalogInfo');
                const catNameEl = document.getElementById('selectedProgCatalogName');
                const countEl = document.getElementById('selectedProgRollsCount');
                if (infoBox && catNameEl) {
                    const firstRoll = data.rolls && data.rolls[0];
                    const catDisplayName = (data.program && data.program.catalog_name) 
                        ? `${data.program.catalog_code ? '[' + data.program.catalog_code + '] ' : ''}${data.program.catalog_name}`
                        : (firstRoll ? (firstRoll.roll_catalog_name || firstRoll.hierarchy_display || 'General / Per Roll') : 'General');
                    catNameEl.textContent = catDisplayName;
                    if (countEl) countEl.textContent = `${(data.rolls || []).length} Roll(s)`;
                    infoBox.classList.remove('d-none');
                }

                renderDynamicBreakdown(data);
                recalcAnalytics();
            })
            .catch(err => {
                console.error(err);
                alert(err.message || 'Unable to load program details. Please try again.');
            });
    });

    function resetToEmptyState() {
        currentProgramData = null;
        document.getElementById('selectedProgramCatalogInfo')?.classList.add('d-none');
        noProgramState.classList.remove('d-none');
        activeProgramContainer.classList.add('d-none');
        rollSelectionBar.classList.add('d-none');
        rollTabsContainer.innerHTML = '';
        rollVariantPanels.innerHTML = '';

        inputTotalRollWeight.value = '0.00';
        inputTotalRollPrice.value = '0.00';
        inputWastageWeight.value = '0.00';

        badgeFabricRateTop.textContent = '₹0.00 / kg';
        badgeWastageCostTop.textContent = 'Wastage Cost: ₹0.00';
        spanWastageTotalCost.textContent = 'Total Cost: ₹0.00';

        statFabricRate.textContent = '₹0.00 / kg';
        statWastageCost.textContent = '₹0.00';
        statWastagePct.textContent = '0.0%';
        statUsedWeight.textContent = '0.00 kg';
        statTotalCutQty.textContent = '0 pcs';
        statFabricCostPerPc.textContent = '₹0.00';
        badgeTotalCutPcs.textContent = '0 Cut pcs';
    }

    function renderDynamicBreakdown(data) {
        noProgramState.classList.add('d-none');
        activeProgramContainer.classList.remove('d-none');
        rollSelectionBar.classList.remove('d-none');

        inputTotalRollWeight.value = parseFloat(data.total_roll_weight || 0).toFixed(2);
        inputTotalRollPrice.value = parseFloat(data.total_roll_price || 0).toFixed(2);

        rollTabsContainer.innerHTML = '';
        rollVariantPanels.innerHTML = '';

        const rolls = data.rolls || [];
        if (rolls.length === 0) {
            rollVariantPanels.innerHTML = '<div class="alert alert-warning">No fabric rolls assigned to this program.</div>';
            return;
        }

        rolls.forEach((roll, idx) => {
            const rollTag = roll.roll_piece_tag ? `[${roll.roll_piece_tag}]` : `Roll #${idx + 1}`;
            const rollLabel = `${rollTag} ${roll.fabric_name || 'Fabric'}`;
            const isActive = idx === 0;

            const catalogName = roll.roll_catalog_name || roll.hierarchy_display || '';
            const catalogCode = roll.roll_style_code || '';
            let catalogBadgeHtml = '';
            if (catalogName) {
                catalogBadgeHtml = `
                    <span class="badge bg-primary text-white border-0 py-1 px-2.5 shadow-xs" title="Assigned Catalog Style">
                        <i class="bi bi-tag-fill me-1"></i> Catalog: ${catalogCode ? `[${catalogCode}] ` : ''}${catalogName}
                    </span>
                `;
            } else {
                catalogBadgeHtml = `<span class="badge bg-white text-secondary border">No Catalog Assigned</span>`;
            }

            // Roll Tab Button
            const tabBtn = document.createElement('button');
            tabBtn.type = 'button';
            tabBtn.className = `btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} rounded-pill px-3 py-1 fw-semibold roll-tab-btn`;
            tabBtn.dataset.rollId = roll.id;
            tabBtn.innerHTML = `<i class="bi bi-box-seam me-1"></i> ${rollLabel} ${catalogCode ? `<span class="badge bg-dark text-white ms-1 font-monospace" style="font-size: 0.70rem;">${catalogCode}</span>` : ''} <span class="badge ${isActive ? 'bg-white text-primary' : 'bg-secondary text-white'} ms-1">${parseFloat(roll.calculated_weight).toFixed(2)} kg</span>`;
            tabBtn.addEventListener('click', () => switchRollTab(roll.id));
            rollTabsContainer.appendChild(tabBtn);

            // Roll Variant Panel
            const panel = document.createElement('div');
            panel.id = `panelRoll_${roll.id}`;
            panel.className = `roll-variant-panel ${isActive ? '' : 'd-none'}`;

            let rowsHtml = '';
            (roll.variants || []).forEach((v, vIdx) => {
                const varId = v.id || '';
                const sizeName = v.size || 'Standard';
                const sku = v.product_code || '';
                const varName = v.name || `${roll.fabric_name} - ${sizeName}`;

                rowsHtml += `
                    <tr>
                        <td class="align-middle">
                            <div class="fw-bold text-dark">${sizeName}</div>
                            <small class="text-muted font-monospace" style="font-size: 0.72rem;">${sku}</small>
                            <input type="hidden" name="rolls[${roll.id}][variants][${vIdx}][variant_id]" value="${varId}">
                            <input type="hidden" name="rolls[${roll.id}][variants][${vIdx}][size]" value="${sizeName}">
                            <input type="hidden" name="rolls[${roll.id}][variants][${vIdx}][product_code]" value="${sku}">
                            <input type="hidden" name="rolls[${roll.id}][variants][${vIdx}][variant_name]" value="${varName}">
                        </td>
                        <td class="align-middle text-muted small font-monospace">
                            ${sku}
                        </td>
                        <td class="align-middle text-end" style="width: 160px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-scissors"></i></span>
                                <input type="number" step="1" min="0" 
                                       name="rolls[${roll.id}][variants][${vIdx}][cut_pieces]" 
                                       class="form-control text-end fw-bold input-cut-pcs" 
                                       value="0" placeholder="0">
                                <span class="input-group-text bg-light text-muted">pcs</span>
                            </div>
                        </td>
                    </tr>
                `;
            });

            panel.innerHTML = `
                <div class="card border rounded-3 p-3 mb-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-bold text-dark fs-6">${rollLabel}</span>
                            <span class="badge bg-white text-secondary border">${roll.color || 'Standard'}</span>
                            ${catalogBadgeHtml}
                        </div>
                        <div class="small text-muted font-monospace">
                            Roll Weight: <strong class="text-primary">${parseFloat(roll.calculated_weight).toFixed(2)} kg</strong> | 
                            Value: <strong class="text-success">₹${parseFloat(roll.calculated_price).toFixed(2)}</strong>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 bg-white border rounded-2">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Variant / Size</th>
                                    <th>Product Code</th>
                                    <th class="text-end pe-3">Cut Output</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            rollVariantPanels.appendChild(panel);
        });

        activeRollId = rolls[0].id;

        // Bind input event listener on cut pcs
        document.querySelectorAll('.input-cut-pcs').forEach(input => {
            input.addEventListener('input', recalcAnalytics);
        });
    }

    function switchRollTab(rollId) {
        activeRollId = rollId;
        document.querySelectorAll('.roll-tab-btn').forEach(btn => {
            const isMatch = btn.dataset.rollId == rollId;
            btn.className = `btn btn-sm ${isMatch ? 'btn-primary' : 'btn-outline-secondary'} rounded-pill px-3 py-1 fw-semibold roll-tab-btn`;
            const badge = btn.querySelector('.badge');
            if (badge) {
                badge.className = `badge ${isMatch ? 'bg-white text-primary' : 'bg-secondary text-white'} ms-1`;
            }
        });

        document.querySelectorAll('.roll-variant-panel').forEach(p => {
            p.classList.add('d-none');
        });
        const targetPanel = document.getElementById(`panelRoll_${rollId}`);
        if (targetPanel) {
            targetPanel.classList.remove('d-none');
        }
    }

    // Wastage weight listener (Strictly Integer)
    inputWastageWeight.addEventListener('keydown', function(e) {
        if (e.key === '.' || e.key === ',' || e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-') {
            e.preventDefault();
        }
    });

    inputWastageWeight.addEventListener('input', function() {
        let val = this.value;
        if (val.includes('.') || val.includes(',')) {
            this.value = Math.floor(parseFloat(val)) || 0;
        }
        recalcAnalytics();
    });

    function recalcAnalytics() {
        if (!currentProgramData) return;

        const totalRollWeight = parseFloat(inputTotalRollWeight.value) || 0;
        const totalRollPrice = parseFloat(inputTotalRollPrice.value) || 0;
        let wastageWeight = parseInt(inputWastageWeight.value, 10);
        if (isNaN(wastageWeight) || wastageWeight < 0) {
            wastageWeight = 0;
        }

        if (totalRollWeight > 0 && wastageWeight > totalRollWeight) {
            wastageExceedMsg.classList.remove('d-none');
            inputWastageWeight.classList.add('is-invalid');
        } else {
            wastageExceedMsg.classList.add('d-none');
            inputWastageWeight.classList.remove('is-invalid');
        }

        const fabricRate = totalRollWeight > 0 ? (totalRollPrice / totalRollWeight) : 0;
        const wastageCost = wastageWeight * fabricRate;
        const wastagePct = totalRollWeight > 0 ? (wastageWeight / totalRollWeight) * 100 : 0;
        const usedWeight = Math.max(0, totalRollWeight - wastageWeight);

        // Sum cut pieces across all variant inputs
        let totalCutPieces = 0;
        document.querySelectorAll('.input-cut-pcs').forEach(inp => {
            totalCutPieces += parseInt(inp.value) || 0;
        });

        const fabricCostPerPiece = totalCutPieces > 0 ? Math.max(0, (totalRollPrice - wastageCost) / totalCutPieces) : 0;

        // Update displays
        badgeFabricRateTop.textContent = `₹${fabricRate.toFixed(2)} / kg`;
        badgeWastageCostTop.textContent = `Wastage Cost: ₹${wastageCost.toFixed(2)}`;
        spanWastageTotalCost.textContent = `Total Cost: ₹${wastageCost.toFixed(2)}`;

        statFabricRate.textContent = `₹${fabricRate.toFixed(2)} / kg`;
        statWastageCost.textContent = `₹${wastageCost.toFixed(2)}`;
        statWastagePct.textContent = `${wastagePct.toFixed(1)}%`;
        statUsedWeight.textContent = `${usedWeight.toFixed(2)} kg`;
        statTotalCutQty.textContent = `${totalCutPieces} pcs`;
        statFabricCostPerPc.textContent = `₹${fabricCostPerPiece.toFixed(2)}`;

        badgeTotalCutPcs.textContent = `${totalCutPieces} Cut pcs`;
    }

    // Modal: Quick Add Cutting Master via AJAX with Inline Mobile Validation
    const btnSaveCuttingMaster = document.getElementById('btnSaveCuttingMaster');
    const newMasterName = document.getElementById('newMasterName');
    const newMasterPhone = document.getElementById('newMasterPhone');
    const newMasterPhoneError = document.getElementById('newMasterPhoneError');
    const newMasterCode = document.getElementById('newMasterCode');
    const masterErrorMsg = document.getElementById('masterErrorMsg');
    const cuttingMasterSelect = document.getElementById('cuttingMasterSelect');

    function validateMasterPhoneInline() {
        const val = newMasterPhone.value.trim();
        if (!val) {
            newMasterPhone.classList.remove('is-invalid', 'is-valid');
            newMasterPhoneError.classList.add('d-none');
            newMasterPhoneError.textContent = '';
            return true;
        }
        if (!/^[6-9]/.test(val)) {
            newMasterPhone.classList.add('is-invalid');
            newMasterPhone.classList.remove('is-valid');
            newMasterPhoneError.classList.remove('d-none');
            newMasterPhoneError.textContent = 'Mobile number must start with 6, 7, 8, or 9.';
            return false;
        }
        if (val.length < 10) {
            newMasterPhone.classList.add('is-invalid');
            newMasterPhone.classList.remove('is-valid');
            newMasterPhoneError.classList.remove('d-none');
            newMasterPhoneError.textContent = `Mobile number must be 10 digits (${val.length}/10 entered).`;
            return false;
        }
        newMasterPhone.classList.remove('is-invalid');
        newMasterPhone.classList.add('is-valid');
        newMasterPhoneError.classList.add('d-none');
        newMasterPhoneError.textContent = '';
        return true;
    }

    newMasterPhone.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
        validateMasterPhoneInline();
    });

    newMasterPhone.addEventListener('blur', function() {
        validateMasterPhoneInline();
    });

    btnSaveCuttingMaster.addEventListener('click', function() {
        const name = newMasterName.value.trim();
        const phone = newMasterPhone.value.trim();
        const code = newMasterCode.value.trim();

        if (!name) {
            masterErrorMsg.textContent = 'Please enter cutting master name.';
            masterErrorMsg.classList.remove('d-none');
            return;
        }

        if (phone && !validateMasterPhoneInline()) {
            newMasterPhone.focus();
            return;
        }

        masterErrorMsg.classList.add('d-none');
        btnSaveCuttingMaster.disabled = true;

        const formData = new FormData();
        formData.append('name', name);
        formData.append('phone', phone);
        formData.append('employee_code', code);
        formData.append('program_id', document.getElementById('programSelect').value);

        fetch('<?= \App\Core\Controller::url('/api/cutting/quick-master') ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSaveCuttingMaster.disabled = false;
            if (data.success) {
                // Add to select and select it
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = `${data.name}${data.phone ? ' (' + data.phone + ')' : ''}`;
                opt.selected = true;
                cuttingMasterSelect.appendChild(opt);

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalAddCuttingMaster'));
                if (modal) modal.hide();

                newMasterName.value = '';
                newMasterPhone.value = '';
                newMasterCode.value = '';
            } else {
                masterErrorMsg.textContent = data.message || 'Failed to save master.';
                masterErrorMsg.classList.remove('d-none');
            }
        })
        .catch(err => {
            btnSaveCuttingMaster.disabled = false;
            masterErrorMsg.textContent = 'Server error occurred.';
            masterErrorMsg.classList.remove('d-none');
        });
    });

    // Form submission validation
    document.getElementById('formCreateCutting').addEventListener('submit', function(e) {
        if (!programSelect.value) {
            e.preventDefault();
            alert('Please choose a Production Program.');
            programSelect.focus();
            return;
        }

        let totalCutPieces = 0;
        document.querySelectorAll('.input-cut-pcs').forEach(inp => {
            totalCutPieces += parseInt(inp.value) || 0;
        });

        if (totalCutPieces <= 0) {
            e.preventDefault();
            alert('Please enter cut pieces for at least one variant/size in Section 3.');
            return;
        }

        const totalRollWeight = parseFloat(inputTotalRollWeight.value) || 0;
        const wastageWeight = parseFloat(inputWastageWeight.value) || 0;
        if (totalRollWeight > 0 && wastageWeight > totalRollWeight) {
            e.preventDefault();
            alert(`Wastage weight (${wastageWeight} kg) cannot exceed total assigned roll weight (${totalRollWeight} kg).`);
            inputWastageWeight.focus();
            return;
        }
    });
});
</script>
