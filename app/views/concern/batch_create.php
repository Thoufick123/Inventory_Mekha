<?php
// app/views/concern/batch_create.php
// Pack Finished Production Batch View (Multi-Variant Packing & Direct Outward Dispatch)

$totalInitialPcs = 0;
$totalInitialTaxable = 0.00;
$totalInitialGst = 0.00;
$totalInitialAmount = 0.00;

foreach ($variantItems as $vi) {
    $qty = (int)$vi['passed_avail'];
    $price = (float)$vi['price'];
    $lineTotal = $qty * $price;
    $lineTaxable = $lineTotal > 0 ? round($lineTotal / 1.05, 2) : 0.00;
    $lineGst = round($lineTotal - $lineTaxable, 2);

    $totalInitialPcs += $qty;
    $totalInitialTaxable += $lineTaxable;
    $totalInitialGst += $lineGst;
    $totalInitialAmount += $lineTotal;
}
?>
<div class="container-fluid px-3 py-2">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small text-muted mb-0">
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/dashboard') ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/catalog') ?>" class="text-decoration-none text-muted">Production</a></li>
            <li class="breadcrumb-item"><a href="<?= \App\Core\Controller::url('/production/batches') ?>" class="text-decoration-none text-muted">Batches</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Pack Batch</li>
        </ol>
    </nav>

    <!-- Page Title -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-box-seam text-success"></i> Pack Finished Production Batch
            </h4>
            <p class="text-secondary small mb-0">Select a QC inspection batch to pack all inspected garment variants into a unified finished goods batch or dispatch directly to concern.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= \App\Core\Controller::url('/production/batches') ?>" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to Batches
            </a>
            <a href="<?= \App\Core\Controller::url('/production/outward') ?>" class="btn btn-outline-info btn-sm px-3 fw-semibold">
                <i class="bi bi-truck me-1"></i> Outward Manifests
            </a>
        </div>
    </div>

    <form action="<?= \App\Core\Controller::url('/production/batches/store') ?>" method="POST" id="formPackBatch" onsubmit="return validateBatchForm(event)">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="program_no" value="<?= htmlspecialchars($programStats['program_no'] ?? 'PRG-GEN') ?>">
        <input type="hidden" name="action" id="formActionType" value="pack_only">

        <div class="row g-4">
            <!-- Left Column: Packing Details & Destination -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <h6 class="mb-0 fw-bold text-dark">Packing Details</h6>
                    </div>
                    <div class="card-body p-3">
                        <!-- QC Inspection Selector -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">
                                Select QC Inspection Batch <span class="text-danger">*</span>
                            </label>
                            <select name="qc_batch_id" id="qcBatchSelect" class="form-select form-select-sm" required onchange="handleQcChange(this)">
                                <?php foreach ($qcList as $q): ?>
                                    <option value="<?= $q['id'] ?>" <?= ((int)$q['id'] === $selectedQcId) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($q['qc_no']) ?> (<?= htmlspecialchars($q['program_no'] ?? 'PRG-GEN') ?> &mdash; <?= htmlspecialchars($q['program_name'] ?? 'Garments') ?>) [<?= (int)($q['passed_rem'] ?? $q['passed_qty']) ?> pcs rem]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small text-muted">All passed variants in this QC batch will load in the packing table.</div>
                        </div>

                        <!-- Program & Product Summary Box -->
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">Program & Product</div>
                            <div class="fw-bold text-dark mb-2" style="font-size:0.92rem;">
                                <?= htmlspecialchars($programStats['program_no']) ?> &mdash; <?= htmlspecialchars($programStats['program_name']) ?>
                            </div>

                            <div class="row g-2 text-center">
                                <div class="col-3">
                                    <div class="bg-white p-1.5 rounded border">
                                        <div class="text-muted" style="font-size:0.68rem;">Total Passed</div>
                                        <div class="fw-bold text-dark" id="statTotalPassed"><?= (int)$programStats['total_passed'] ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white p-1.5 rounded border">
                                        <div class="text-muted" style="font-size:0.68rem;">Packed</div>
                                        <div class="fw-bold" id="statPacked" style="color: #ef4444;"><?= (int)$programStats['packed'] ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white p-1.5 rounded border">
                                        <div class="text-muted" style="font-size:0.68rem;">Passed Rem.</div>
                                        <div class="fw-bold" id="statPassedRem" style="color: #10b981;"><?= (int)$programStats['passed_rem'] ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white p-1.5 rounded border">
                                        <div class="text-muted" style="font-size:0.68rem;">Rework</div>
                                        <div class="fw-bold" id="statRework" style="color: #f59e0b;"><?= (int)$programStats['rework'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Destination Warehouse -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">
                                Destination Warehouse <span class="text-danger">*</span>
                            </label>
                            <select name="warehouse_name" id="destWarehouse" class="form-select form-select-sm" required>
                                <option value="" disabled>-- Select Warehouse --</option>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= htmlspecialchars($wh['name']) ?>" selected>
                                        <?= htmlspecialchars($wh['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Total Box / Carton Count -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">
                                Total Box / Carton Count <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="total_boxes" id="totalBoxes" class="form-control form-control-sm" value="1" min="1" required>
                            <div class="form-text small text-muted">Number of packing boxes/cartons for this batch.</div>
                        </div>

                        <hr class="my-3">

                        <!-- Direct Outward to Concern Section -->
                        <div class="p-3 rounded-3" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                            <label class="form-label small fw-bold text-success d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-truck"></i> Destination Concern (Direct Outward)
                            </label>
                            <div class="small text-muted mb-2" style="font-size:0.75rem;">
                                Select a concern to automatically dispatch and generate the Delivery Challan & Gate Pass upon packing:
                            </div>
                            <select name="destination_concern_id" id="destConcernSelect" class="form-select form-select-sm mb-2" onchange="toggleOutwardFields(this)">
                                <option value="">-- Keep in Warehouse (No Direct Outward) --</option>
                                <?php foreach ($allConcerns as $con): ?>
                                    <option value="<?= $con['id'] ?>" <?= ((int)$con['id'] === (int)($currentConcernId ?? 0)) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div id="outwardTransportFields" class="mt-2">
                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-0" style="font-size:0.72rem;">Vehicle Number</label>
                                    <input type="text" name="vehicle_number" class="form-control form-control-sm font-monospace" placeholder="e.g. TN-38-BK-4421" value="TN-38-BK-4421">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-0" style="font-size:0.72rem;">Driver Name</label>
                                        <input type="text" name="driver_name" class="form-control form-control-sm" placeholder="e.g. Murugan" value="Murugan">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-muted mb-0" style="font-size:0.72rem;">Driver Contact</label>
                                        <input type="text" name="driver_contact" class="form-control form-control-sm" placeholder="e.g. 9842109876" value="9842109876">
                                    </div>
                                </div>
                                <div class="mb-1">
                                    <label class="form-label small text-muted mb-0" style="font-size:0.72rem;">Transporter / Carrier</label>
                                    <input type="text" name="transporter_name" class="form-control form-control-sm" placeholder="e.g. Express Logistics" value="Express Logistics">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Right Column: Multi-Variant Packed Quantities Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-grid-3x3 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark">Packed Quantities by Variant</h6>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0.5 px-2.5" style="font-size:0.75rem;" onclick="packAllRemaining()">
                                <i class="bi bi-check-all me-1"></i> Pack All Max
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0.5 px-2.5" style="font-size:0.75rem;" onclick="clearAllQuantities()">
                                Clear
                            </button>
                            <span class="badge px-2.5 py-1.5 fw-bold" id="badgeHeaderPacked" style="background:#10b981; color:#fff;">
                                <?= $totalInitialPcs ?> pcs Packed
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">

                        <?php if (empty($variantItems)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-exclamation-circle fs-2 text-warning d-block mb-2"></i>
                                No passed variants available for packing in this QC inspection batch.
                            </div>
                        <?php else: ?>
                            <!-- Multi-Variant Packing Table -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                                        <tr>
                                            <th style="width: 28%;">VARIANT & SKU</th>
                                            <th style="width: 27%;">QC PASSED STOCK</th>
                                            <th style="width: 15%;" class="text-end">UNIT RATE</th>
                                            <th style="width: 15%;" class="text-end">QTY TO PACK *</th>
                                            <th style="width: 15%;" class="text-end">LINE TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($variantItems as $idx => $vItem): 
                                            $initQty = (int)$vItem['passed_avail'];
                                            $vPrice = (float)$vItem['price'];
                                            $vLineTotal = $initQty * $vPrice;
                                        ?>
                                            <tr>
                                                <td class="align-middle py-3">
                                                    <div class="fw-bold text-dark fs-6">
                                                        <?= htmlspecialchars($vItem['size'] ?? 'Standard') ?> / <?= htmlspecialchars($vItem['color'] ?? 'Standard') ?>
                                                    </div>
                                                    <div class="small text-muted font-monospace"><?= htmlspecialchars($vItem['sku_code'] ?? '') ?></div>
                                                    <div class="small text-secondary" style="font-size:0.75rem;"><?= htmlspecialchars($vItem['name'] ?? '') ?></div>
                                                </td>
                                                <td class="align-middle py-3">
                                                    <?php if ($vItem['passed_avail'] > 0): ?>
                                                        <span class="badge px-2 py-1 fw-semibold mb-1 d-inline-block" style="background:#d1fae5; color:#065f46;">
                                                            <i class="bi bi-check-circle me-1"></i> <span id="labelAvail_<?= $vItem['variant_id'] ?>"><?= (int)$vItem['passed_avail'] ?></span> pcs Available
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary text-white px-2 py-1 mb-1 d-inline-block">
                                                            Fully Packed (0 pcs rem)
                                                        </span>
                                                    <?php endif; ?>
                                                    <div class="small text-muted" style="font-size:0.75rem;">
                                                        Passed: <?= (int)$vItem['total_passed'] ?> pcs (<?= (int)$vItem['already_packed'] ?> packed)
                                                    </div>
                                                </td>
                                                <td class="align-middle py-3 text-end font-monospace text-secondary">
                                                    ₹<?= number_format($vPrice, 2) ?>
                                                </td>
                                                <td class="align-middle py-3 text-end">
                                                    <input type="hidden" name="items[<?= $vItem['variant_id'] ?>][variant_id]" value="<?= $vItem['variant_id'] ?>">
                                                    <input type="hidden" name="items[<?= $vItem['variant_id'] ?>][size]" value="<?= htmlspecialchars($vItem['size'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $vItem['variant_id'] ?>][color]" value="<?= htmlspecialchars($vItem['color'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $vItem['variant_id'] ?>][sku_code]" value="<?= htmlspecialchars($vItem['sku_code'] ?? '') ?>">
                                                    <input type="hidden" name="items[<?= $vItem['variant_id'] ?>][unit_price]" value="<?= $vPrice ?>">

                                                    <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                                        <input type="number" 
                                                               name="items[<?= $vItem['variant_id'] ?>][qty]" 
                                                               id="inputQty_<?= $vItem['variant_id'] ?>" 
                                                               class="form-control form-control-sm text-end fw-bold variant-qty-input font-monospace" 
                                                               style="width: 80px;" 
                                                               value="<?= $initQty ?>" 
                                                               min="0" 
                                                               max="<?= $vItem['passed_avail'] ?>" 
                                                               data-variant-id="<?= $vItem['variant_id'] ?>"
                                                               data-unit-price="<?= $vPrice ?>"
                                                               data-max="<?= $vItem['passed_avail'] ?>"
                                                               oninput="handleVariantQtyChange(this)">
                                                        <span class="small text-muted">pcs</span>
                                                    </div>
                                                    <div class="small text-muted mt-0.5" style="font-size:0.7rem;">Max: <?= (int)$vItem['passed_avail'] ?></div>
                                                </td>
                                                <td class="align-middle py-3 text-end font-monospace fw-bold text-dark">
                                                    ₹<span id="lineTotal_<?= $vItem['variant_id'] ?>"><?= number_format($vLineTotal, 2) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Horizontal Invoice & Financial Summary Bar -->
                        <div class="p-3 bg-light rounded-3 border mt-4">
                            <div class="row g-3 text-center align-items-center">
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-box me-1"></i> PRODUCT COUNT
                                    </div>
                                    <div class="fw-bold text-dark fs-5" id="footerCount"><?= $totalInitialPcs ?> pcs</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-currency-rupee me-0.5"></i> TAXABLE VALUE
                                    </div>
                                    <div class="fw-bold text-dark fs-5">₹<span id="footerTaxable"><?= number_format($totalInitialTaxable, 2) ?></span></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-percent me-0.5"></i> GST (5% INCL.)
                                    </div>
                                    <div class="fw-bold fs-5" style="color: #2563eb;">₹<span id="footerGst"><?= number_format($totalInitialGst, 2) ?></span></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-file-earmark-check me-0.5"></i> TOTAL BILL & INVOICE
                                    </div>
                                    <div class="fw-bold fs-5" style="color: #10b981;">₹<span id="footerTotal"><?= number_format($totalInitialAmount, 2) ?></span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Action Buttons -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 pt-2 border-top">
                            <div>
                                <a href="<?= \App\Core\Controller::url('/production/batches') ?>" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                                    <i class="bi bi-x-circle me-1"></i> Cancel
                                </a>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-success btn-sm px-3 fw-semibold" onclick="openBillPreviewModal()">
                                    <i class="bi bi-receipt me-1"></i> Preview Invoice
                                </button>
                                <button type="submit" onclick="document.getElementById('formActionType').value='pack_only';" class="btn btn-outline-primary btn-sm px-3 fw-semibold">
                                    <i class="bi bi-box-seam me-1"></i> Save Packed Batch Only
                                </button>
                                <button type="submit" onclick="document.getElementById('formActionType').value='pack_and_outward';" class="btn btn-success btn-sm px-4 fw-bold shadow-sm" style="background:#10b981; border-color:#10b981;">
                                    <i class="bi bi-truck me-1"></i> Pack & Outward to Concern
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal: Bill & Invoice Draft Preview -->
<div class="modal fade" id="modalBillPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:#10b981;">
                <h6 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i> Packing Slip & Invoice Draft Preview</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Program & Style</span>
                    <span class="fw-bold text-dark font-monospace"><?= htmlspecialchars($programStats['program_no']) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Target Destination</span>
                    <span class="fw-semibold text-primary" id="previewDestination">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Total Pieces to Pack</span>
                    <span class="fw-bold fs-6 text-dark" id="previewPieces">0 pcs</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Taxable Value</span>
                    <span class="fw-bold text-dark">₹<span id="previewTaxable">0.00</span></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">GST (5% Inclusive)</span>
                    <span class="fw-bold text-primary">₹<span id="previewGst">0.00</span></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 bg-light rounded px-3">
                    <span class="fw-bold text-dark">Total Net Bill & Invoice</span>
                    <span class="fw-bold fs-5 text-success">₹<span id="previewTotal">0.00</span></span>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success btn-sm px-3" onclick="document.getElementById('formPackBatch').submit();">
                    <i class="bi bi-check2-circle me-1"></i> Confirm & Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function handleQcChange(select) {
    if (select.value) {
        window.location.href = '<?= \App\Core\Controller::url('/production/batches/create') ?>?qc_batch_id=' + select.value;
    }
}

function toggleOutwardFields(select) {
    var fields = document.getElementById('outwardTransportFields');
    if (fields) {
        fields.style.display = select.value ? 'block' : 'none';
    }
}

function handleVariantQtyChange(input) {
    var val = parseInt(input.value) || 0;
    var max = parseInt(input.getAttribute('data-max')) || 0;
    if (val < 0) val = 0;
    if (val > max) val = max;
    input.value = val;

    var vId = input.getAttribute('data-variant-id');
    var price = parseFloat(input.getAttribute('data-unit-price')) || 0;
    var lineTotal = val * price;

    var lineSpan = document.getElementById('lineTotal_' + vId);
    if (lineSpan) {
        lineSpan.innerText = lineTotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    calculateAllTotals();
}

function calculateAllTotals() {
    var inputs = document.querySelectorAll('.variant-qty-input');
    var totalPieces = 0;
    var grandTotal = 0.00;

    inputs.forEach(function(inp) {
        var qty = parseInt(inp.value) || 0;
        var price = parseFloat(inp.getAttribute('data-unit-price')) || 0;
        totalPieces += qty;
        grandTotal += (qty * price);
    });

    var gstRate = 0.05;
    var taxable = grandTotal > 0 ? (grandTotal / (1 + gstRate)) : 0;
    var gst = grandTotal - taxable;

    document.getElementById('badgeHeaderPacked').innerText = totalPieces + ' pcs Packed';
    document.getElementById('footerCount').innerText = totalPieces + ' pcs';
    document.getElementById('footerTaxable').innerText = taxable.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('footerGst').innerText = gst.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('footerTotal').innerText = grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function packAllRemaining() {
    var inputs = document.querySelectorAll('.variant-qty-input');
    inputs.forEach(function(inp) {
        var max = parseInt(inp.getAttribute('data-max')) || 0;
        inp.value = max;
        var vId = inp.getAttribute('data-variant-id');
        var price = parseFloat(inp.getAttribute('data-unit-price')) || 0;
        var lineSpan = document.getElementById('lineTotal_' + vId);
        if (lineSpan) {
            lineSpan.innerText = (max * price).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    });
    calculateAllTotals();
}

function clearAllQuantities() {
    var inputs = document.querySelectorAll('.variant-qty-input');
    inputs.forEach(function(inp) {
        inp.value = 0;
        var vId = inp.getAttribute('data-variant-id');
        var lineSpan = document.getElementById('lineTotal_' + vId);
        if (lineSpan) {
            lineSpan.innerText = '0.00';
        }
    });
    calculateAllTotals();
}

function openBillPreviewModal() {
    var count = document.getElementById('footerCount').innerText;
    var destSel = document.getElementById('destConcernSelect');
    var wh = document.getElementById('destWarehouse').value || 'Main Warehouse';
    var destLabel = wh;
    if (destSel && destSel.value) {
        destLabel = destSel.options[destSel.selectedIndex].text;
    }

    document.getElementById('previewDestination').innerText = destLabel;
    document.getElementById('previewPieces').innerText = count;
    document.getElementById('previewTaxable').innerText = document.getElementById('footerTaxable').innerText;
    document.getElementById('previewGst').innerText = document.getElementById('footerGst').innerText;
    document.getElementById('previewTotal').innerText = document.getElementById('footerTotal').innerText;

    new bootstrap.Modal(document.getElementById('modalBillPreview')).show();
}

function validateBatchForm(event) {
    var boxes = parseInt(document.getElementById('totalBoxes').value) || 0;
    var wh = document.getElementById('destWarehouse').value;
    var inputs = document.querySelectorAll('.variant-qty-input');
    var totalQty = 0;

    inputs.forEach(function(inp) {
        totalQty += (parseInt(inp.value) || 0);
    });

    if (!wh) {
        alert("Please select a Destination Warehouse.");
        event.preventDefault();
        return false;
    }
    if (boxes <= 0) {
        alert("Please enter a valid Total Box / Carton Count.");
        event.preventDefault();
        return false;
    }
    if (totalQty <= 0) {
        alert("Please enter at least 1 piece to pack across the variants.");
        event.preventDefault();
        return false;
    }
    return true;
}
</script>
