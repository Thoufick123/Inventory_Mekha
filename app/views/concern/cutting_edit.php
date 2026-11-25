<?php
/**
 * View: Edit Production Cutting Batch
 * Location: inventory_mekha/app/views/concern/cutting_edit.php
 */
$csrfToken = \App\Core\Csrf::token();
$availScrap = max(0.00, (float)$batch['wastage_qty'] - (float)$batch['sold_wastage_qty']);
$soldScrap = (float)($batch['sold_wastage_qty'] ?? 0.00);
$totalRollWeight = (float)($batch['total_roll_weight'] ?? 0.00);
$totalRollPrice = (float)($batch['total_roll_price'] ?? 0.00);
if ($totalRollWeight <= 0 && !empty($rolls)) {
    $totalRollWeight = (float)array_sum(array_column($rolls, 'calculated_weight'));
}
if ($totalRollPrice <= 0 && !empty($rolls)) {
    $totalRollPrice = (float)array_sum(array_column($rolls, 'calculated_price'));
}
$fabricRatePerKg = (float)($batch['fabric_rate_per_kg'] ?? ($totalRollWeight > 0 ? $totalRollPrice / $totalRollWeight : 0.00));
if ($fabricRatePerKg <= 0 && $totalRollWeight > 0 && $totalRollPrice > 0) {
    $fabricRatePerKg = $totalRollPrice / $totalRollWeight;
}
$wastageWeight = (int)floor((float)($batch['wastage_qty'] ?? 0));
$wastageCost = (float)($batch['wastage_cost'] ?? ($wastageWeight * $fabricRatePerKg));
$wastagePct = (float)($batch['wastage_percent'] ?? ($totalRollWeight > 0 ? ($wastageWeight / $totalRollWeight) * 100 : 0.00));
$usedWeight = (float)($batch['used_weight'] ?? max(0.00, $totalRollWeight - $wastageWeight));
$totalCutPieces = (int)($batch['cut_pieces'] ?? 0);
$fabricCostPerPiece = (float)($batch['fabric_cost_per_piece'] ?? ($totalCutPieces > 0 ? ($totalRollPrice - $wastageCost) / $totalCutPieces : 0.00));
?>
<div class="container-fluid px-4 py-3">

    <!-- BREADCRUMBS -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="text-decoration-none text-muted">Cutting</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Edit <?= htmlspecialchars($batch['batch_no'] ?: $batch['log_no']) ?></li>
        </ol>
    </nav>

    <!-- HEADER BAR -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary font-monospace px-3 py-1.5 fs-6">
                    <?= htmlspecialchars($batch['batch_no'] ?: $batch['log_no']) ?>
                </span>
                <h4 class="fw-bold mb-0 text-dark">Edit Cutting Batch</h4>
            </div>
            <p class="text-secondary small mb-0">
                Update cutting log details, fabric wastage weight, and variant cutting breakdown for 
                <strong><?= htmlspecialchars($batch['program_no'] ?? 'Program') ?></strong>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="btn btn-light border shadow-sm fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to Batches
            </a>
            <a href="<?= \App\Core\Controller::url('/production/cutting/view?id=' . $batch['id']) ?>" class="btn btn-outline-secondary shadow-sm fw-semibold">
                <i class="bi bi-eye me-1"></i> View Batch
            </a>
        </div>
    </div>

    <!-- MAIN FORM -->
    <form method="POST" action="<?= \App\Core\Controller::url('/production/cutting/update') ?>" id="formEditCutting">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= (int)$batch['id'] ?>">

        <div class="row g-4">
            
            <!-- ========================================== -->
            <!-- LEFT COLUMN: 1. OVERVIEW & 2. WASTAGE      -->
            <!-- ========================================== -->
            <div class="col-lg-6">

                <!-- 1. BATCH OVERVIEW & ASSIGNMENT -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <span class="badge rounded-circle bg-primary-subtle text-primary p-1">
                                <i class="bi bi-sliders fs-6"></i>
                            </span>
                            1. Batch Overview & Assignment
                        </h6>
                    </div>
                    <div class="card-body p-4">

                        <!-- Program Info (Readonly Context) -->
                        <div class="bg-light border rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase;">Production Program</small>
                                    <span class="fw-bold font-monospace text-primary fs-6"><?= htmlspecialchars($batch['program_no'] ?? '-') ?></span>
                                    <span class="text-muted small ms-2">&bull; <?= htmlspecialchars($batch['program_name'] ?? '') ?></span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    Planned: <?= number_format((float)($batch['planned_qty'] ?? 0)) ?> pcs
                                </span>
                            </div>
                        </div>

                        <!-- Cutting Name (Required) -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Cutting Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="cutting_name" id="cuttingName" class="form-control" 
                                   value="<?= htmlspecialchars($batch['cutting_name']) ?>" required>
                        </div>

                        <!-- Status & Cutting Master Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Status</label>
                                <select name="status" id="cuttingStatus" class="form-select">
                                    <option value="in_progress" <?= $batch['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="planned" <?= $batch['status'] === 'planned' ? 'selected' : '' ?>>Planned</option>
                                    <option value="completed" <?= $batch['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
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
                                        <option value="<?= $cm['id'] ?>" <?= ((int)$batch['cutting_master_id'] === (int)$cm['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cm['name']) ?><?= !empty($cm['phone']) ? ' (' . htmlspecialchars($cm['phone']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Notes / Remarks -->
                        <div class="mb-0">
                            <label class="form-label small fw-semibold text-dark mb-1">Notes / Instructions</label>
                            <textarea name="notes" rows="2" class="form-control" placeholder="Optional notes for cutting room or master"><?= htmlspecialchars($batch['notes'] ?? '') ?></textarea>
                        </div>

                    </div>
                </div>

                <!-- 2. FABRIC WASTAGE & COST ANALYTICS (Matching Image 2) -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <span class="badge rounded-circle bg-warning-subtle text-warning p-1">
                                <i class="bi bi-calculator fs-6"></i>
                            </span>
                            2. Fabric Wastage & Cost Analytics
                        </h6>
                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1 font-monospace" id="badgeFabricRateTop">
                            ₹<?= number_format($fabricRatePerKg, 2) ?> / kg
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-3">Calculate multi-roll fabric wastage, rate per kg & exact wastage costs.</p>

                        <!-- Total Roll Weight (kg) & Total Roll Price (₹) -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Total Roll Weight (kg)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" class="form-control bg-light fw-bold text-dark" 
                                           id="inputTotalRollWeight" value="<?= number_format($totalRollWeight, 2, '.', '') ?>" readonly tabindex="-1">
                                    <span class="input-group-text bg-light text-muted">kg</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark mb-1">Total Roll Price (₹)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted">₹</span>
                                    <input type="number" step="0.01" class="form-control bg-light fw-bold font-monospace text-dark" 
                                           id="inputTotalRollPrice" value="<?= number_format($totalRollPrice, 2, '.', '') ?>" readonly tabindex="-1">
                                </div>
                            </div>
                        </div>

                        <!-- Fabric Roll Wastage Breakdown Title & Badge -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold text-danger d-flex align-items-center gap-1.5">
                                <i class="bi bi-trash3 text-danger"></i> Fabric Roll Wastage Breakdown
                            </span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" id="badgeWastageCostTop">
                                Wastage Cost: ₹<?= number_format($wastageCost, 2) ?>
                            </span>
                        </div>

                        <!-- Prominent Red Wastage Input Card matching Image 2 (Strictly Integer) -->
                        <div class="card bg-danger-subtle border border-danger-subtle rounded-3 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-danger mb-0">Total Wastage Fabric Weight (kg)</label>
                                <span class="small fw-bold text-danger" id="spanWastageTotalCost">Total Cost: ₹<?= number_format($wastageCost, 2) ?></span>
                            </div>
                            <div class="input-group input-group-lg my-1">
                                <input type="number" step="1" min="0" class="form-control fw-bolder text-danger fs-3 bg-white border-danger" 
                                       id="inputWastageWeight" name="wastage_qty" value="<?= $wastageWeight ?>" placeholder="0" required>
                                <span class="input-group-text bg-danger text-white fw-bold fs-5">kg</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted" style="font-size: 0.75rem;">Total end cuts, scraps & defective cloth remaining across all selected rolls.</small>
                                <?php if ($soldScrap > 0.01): ?>
                                    <small class="badge bg-white text-secondary border font-monospace" style="font-size: 0.70rem;">
                                        Scrap Sold: <?= number_format($soldScrap, 2) ?> kg
                                    </small>
                                <?php endif; ?>
                            </div>
                            <small class="text-danger small d-none mt-1 fw-semibold" id="wastageExceedMsg">
                                <i class="bi bi-exclamation-circle me-1"></i> Wastage weight cannot exceed total assigned roll weight!
                            </small>
                            <small class="text-danger small d-none mt-1 fw-semibold" id="wastageUnderSoldMsg">
                                <i class="bi bi-exclamation-circle me-1"></i> Wastage weight cannot be less than already sold scrap (<?= number_format($soldScrap, 2) ?> kg)!
                            </small>
                        </div>

                        <!-- 6-Grid Stats Cards matching Image 2 -->
                        <div class="row g-2 mb-4">
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Fabric Rate</small>
                                    <span class="fw-bold fs-6 text-dark" id="statFabricRate">₹<?= number_format($fabricRatePerKg, 2) ?> / kg</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Wastage Cost</small>
                                    <span class="fw-bold fs-6 text-danger" id="statWastageCost">₹<?= number_format($wastageCost, 2) ?></span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Wastage %</small>
                                    <span class="fw-bold fs-6 text-warning" id="statWastagePct"><?= number_format($wastagePct, 1) ?>%</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Used Weight</small>
                                    <span class="fw-bold fs-6 text-primary" id="statUsedWeight"><?= number_format($usedWeight, 2) ?> kg</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Total Cut Qty</small>
                                    <span class="fw-bold fs-6 text-success" id="statTotalCutQty"><?= number_format($totalCutPieces) ?> pcs</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2.5 bg-white text-center shadow-2xs">
                                    <small class="text-muted d-block small mb-1">Fabric Cost / Pc</small>
                                    <span class="fw-bold fs-6 text-info" id="statFabricCostPerPc">₹<?= number_format($fabricCostPerPiece, 2) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Action Buttons matching Image 2 -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <a href="<?= \App\Core\Controller::url('/production/cutting') ?>" class="btn btn-light border px-4 py-2 fw-semibold text-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" id="btnUpdateBatch">
                                <i class="bi bi-check2-circle fs-5"></i> Update Cutting Batch
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
                    
                    <!-- Header -->
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <span class="badge rounded-circle bg-primary-subtle text-primary p-1">
                                    <i class="bi bi-grid-3x3-gap-fill fs-6"></i>
                                </span>
                                3. Dynamic Variant Cutting Breakdown
                            </h6>
                            <small class="text-muted" style="font-size: 0.75rem;">Variants for each selected fabric roll based on catalog hierarchy</small>
                        </div>
                        <span class="badge bg-success px-3 py-1.5 fs-6 fw-bold shadow-sm" id="badgeTotalCutPcs">
                            <?= number_format($totalCutPieces) ?> Cut pcs
                        </span>
                    </div>

                    <!-- SELECT ROLL sub-bar -->
                    <div class="px-3 py-2 bg-light border-bottom" id="rollSelectionBar">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small fw-bold text-muted text-uppercase" style="font-size: 0.70rem; letter-spacing: 0.5px;">
                                <i class="bi bi-layers me-1"></i> SELECT ROLL:
                            </span>
                            <div class="d-flex gap-2 flex-wrap" id="rollTabsContainer">
                                <?php foreach ($rolls as $idx => $roll): 
                                    $rTag = !empty($roll['roll_piece_tag']) ? '[' . htmlspecialchars($roll['roll_piece_tag']) . ']' : ('Roll #' . ($idx + 1));
                                    $rLabel = $rTag . ' ' . htmlspecialchars($roll['fabric_name'] ?? 'Fabric');
                                    $isActive = ($idx === 0);
                                    $styleCode = htmlspecialchars($roll['roll_style_code'] ?? '');
                                ?>
                                    <button type="button" 
                                            class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3 py-1 fw-semibold roll-tab-btn" 
                                            data-roll-id="<?= $roll['id'] ?>" 
                                            onclick="switchRollTab(<?= $roll['id'] ?>)">
                                        <i class="bi bi-box-seam me-1"></i> <?= $rLabel ?>
                                        <?php if (!empty($styleCode)): ?>
                                            <span class="badge bg-dark text-white ms-1 font-monospace" style="font-size: 0.70rem;"><?= $styleCode ?></span>
                                        <?php endif; ?>
                                        <span class="badge <?= $isActive ? 'bg-white text-primary' : 'bg-secondary text-white' ?> ms-1">
                                            <?= number_format((float)($roll['calculated_weight'] ?? 0), 2) ?> kg
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- DYNAMIC ROLL VARIANT TABLES -->
                    <div id="activeProgramContainer" class="card-body p-3 flex-grow-1">
                        <div id="rollVariantPanels">
                            <?php foreach ($rolls as $idx => $roll): 
                                $isActive = ($idx === 0);
                                $rTag = !empty($roll['roll_piece_tag']) ? '[' . htmlspecialchars($roll['roll_piece_tag']) . ']' : ('Roll #' . ($idx + 1));
                                $rLabel = $rTag . ' ' . htmlspecialchars($roll['fabric_name'] ?? 'Fabric');
                                $catalogName = htmlspecialchars($roll['roll_catalog_name'] ?? '');
                                $catalogCode = htmlspecialchars($roll['roll_style_code'] ?? '');
                            ?>
                                <div id="panelRoll_<?= $roll['id'] ?>" class="roll-variant-panel <?= $isActive ? '' : 'd-none' ?>">
                                    <div class="card border rounded-3 p-3 mb-3 bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="fw-bold text-dark fs-6"><?= $rLabel ?></span>
                                                <span class="badge bg-white text-secondary border"><?= htmlspecialchars($roll['color'] ?? 'Standard') ?></span>
                                                <?php if (!empty($catalogName)): ?>
                                                    <span class="badge bg-primary text-white border-0 py-1 px-2.5 shadow-xs" title="Assigned Catalog Style">
                                                        <i class="bi bi-tag-fill me-1"></i> Catalog: <?= $catalogCode ? '[' . $catalogCode . '] ' : '' ?><?= $catalogName ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-white text-secondary border">No Catalog Assigned</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted font-monospace">
                                                Roll Weight: <strong class="text-primary"><?= number_format((float)($roll['calculated_weight'] ?? 0), 2) ?> kg</strong> | 
                                                Value: <strong class="text-success">₹<?= number_format((float)($roll['calculated_price'] ?? 0), 2) ?></strong>
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
                                                    <?php 
                                                    $variants = $roll['variants'] ?? [];
                                                    if (empty($variants)): ?>
                                                        <tr>
                                                            <td colspan="3" class="text-center text-muted py-3 small">
                                                                No variants configured for this roll.
                                                            </td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($variants as $vIdx => $v): 
                                                            $sizeName = htmlspecialchars($v['size'] ?? 'Standard');
                                                            $sku = htmlspecialchars($v['product_code'] ?? '');
                                                            $varName = htmlspecialchars($v['name'] ?? ($roll['fabric_name'] . ' - ' . $sizeName));
                                                            $cutPcs = (int)($v['cut_pieces'] ?? 0);
                                                        ?>
                                                            <tr>
                                                                <td class="align-middle">
                                                                    <div class="fw-bold text-dark"><?= $sizeName ?></div>
                                                                    <small class="text-muted font-monospace" style="font-size: 0.72rem;"><?= $sku ?></small>
                                                                    <input type="hidden" name="rolls[<?= $roll['id'] ?>][variants][<?= $vIdx ?>][variant_id]" value="<?= htmlspecialchars((string)($v['id'] ?? '')) ?>">
                                                                    <input type="hidden" name="rolls[<?= $roll['id'] ?>][variants][<?= $vIdx ?>][size]" value="<?= $sizeName ?>">
                                                                    <input type="hidden" name="rolls[<?= $roll['id'] ?>][variants][<?= $vIdx ?>][product_code]" value="<?= $sku ?>">
                                                                    <input type="hidden" name="rolls[<?= $roll['id'] ?>][variants][<?= $vIdx ?>][variant_name]" value="<?= $varName ?>">
                                                                </td>
                                                                <td class="align-middle text-muted small font-monospace">
                                                                    <?= $sku ?>
                                                                </td>
                                                                <td class="align-middle text-end" style="width: 160px;">
                                                                    <div class="input-group input-group-sm">
                                                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-scissors"></i></span>
                                                                        <input type="number" step="1" min="0" 
                                                                               name="rolls[<?= $roll['id'] ?>][variants][<?= $vIdx ?>][cut_pieces]" 
                                                                               class="form-control text-end fw-bold input-cut-pcs" 
                                                                               value="<?= $cutPcs ?>" placeholder="0">
                                                                        <span class="input-group-text bg-light text-muted">pcs</span>
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
                            <?php endforeach; ?>
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
    const inputTotalRollWeight = document.getElementById('inputTotalRollWeight');
    const inputTotalRollPrice = document.getElementById('inputTotalRollPrice');
    const inputWastageWeight = document.getElementById('inputWastageWeight');
    const wastageExceedMsg = document.getElementById('wastageExceedMsg');
    const wastageUnderSoldMsg = document.getElementById('wastageUnderSoldMsg');

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

    const soldScrapQty = <?= json_encode($soldScrap) ?>;

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

    // Variant cut pieces inputs listener
    document.querySelectorAll('.input-cut-pcs').forEach(input => {
        input.addEventListener('input', recalcAnalytics);
    });

    function recalcAnalytics() {
        const totalRollWeight = parseFloat(inputTotalRollWeight.value) || 0;
        const totalRollPrice = parseFloat(inputTotalRollPrice.value) || 0;
        let wastageWeight = parseInt(inputWastageWeight.value, 10);
        if (isNaN(wastageWeight) || wastageWeight < 0) {
            wastageWeight = 0;
        }

        let hasError = false;
        if (totalRollWeight > 0 && wastageWeight > totalRollWeight) {
            wastageExceedMsg.classList.remove('d-none');
            inputWastageWeight.classList.add('is-invalid');
            hasError = true;
        } else {
            wastageExceedMsg.classList.add('d-none');
        }

        if (wastageWeight < soldScrapQty) {
            wastageUnderSoldMsg?.classList.remove('d-none');
            inputWastageWeight.classList.add('is-invalid');
            hasError = true;
        } else {
            wastageUnderSoldMsg?.classList.add('d-none');
        }

        if (!hasError) {
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
        formData.append('program_id', <?= (int)($batch['program_id'] ?? 0) ?>);

        fetch('<?= \App\Core\Controller::url('/api/cutting/quick-master') ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSaveCuttingMaster.disabled = false;
            if (data.success) {
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = `${data.name}${data.phone ? ' (' + data.phone + ')' : ''}`;
                opt.selected = true;
                cuttingMasterSelect.appendChild(opt);

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
    document.getElementById('formEditCutting').addEventListener('submit', function(e) {
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

        if (wastageWeight < soldScrapQty) {
            e.preventDefault();
            alert(`Wastage weight (${wastageWeight} kg) cannot be less than already sold scrap (${soldScrapQty} kg).`);
            inputWastageWeight.focus();
            return;
        }
    });

    // Initialize initial calculation
    recalcAnalytics();
});

// Global switchRollTab for roll pills
function switchRollTab(rollId) {
    document.querySelectorAll('.roll-tab-btn').forEach(btn => {
        const isMatch = btn.dataset.rollId == rollId;
        btn.className = `btn btn-sm ${isMatch ? 'btn-primary' : 'btn-outline-secondary'} rounded-pill px-3 py-1 fw-semibold roll-tab-btn`;
        const badges = btn.querySelectorAll('.badge');
        if (badges.length > 0) {
            const lastBadge = badges[badges.length - 1];
            lastBadge.className = `badge ${isMatch ? 'bg-white text-primary' : 'bg-secondary text-white'} ms-1`;
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
</script>
