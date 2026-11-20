<?php
// app/views/concern/batch_create.php
// Pack Finished Production Batch View (Image 2)
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
    <h4 class="fw-bold mb-4 text-dark d-flex align-items-center gap-2">
        <i class="bi bi-box-seam text-success"></i> Pack Finished Production Batch
    </h4>

    <form action="<?= \App\Core\Controller::url('/m/batch/store') ?>" method="POST" id="formPackBatch" onsubmit="return validateBatchForm(event)">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="program_no" value="<?= htmlspecialchars($programStats['program_no']) ?>">
        <input type="hidden" name="variant_id" value="<?= (int)$variant['id'] ?>">
        <input type="hidden" name="taxable_value" id="inputTaxable" value="16666.67">
        <input type="hidden" name="gst_amount" id="inputGst" value="833.33">
        <input type="hidden" name="total_amount" id="inputTotal" value="17500.00">

        <div class="row g-4">
            <!-- Left Column: Packing Details -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <h6 class="mb-0 fw-bold text-dark">Packing Details</h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- QC Inspection Selector -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">
                                Select QC Inspection Batch <span class="text-danger">*</span>
                            </label>
                            <select name="qc_batch_id" id="qcBatchSelect" class="form-select form-select-sm" required onchange="handleQcChange(this)">
                                <?php foreach ($qcList as $q): ?>
                                    <option value="<?= $q['id'] ?>" <?= ((int)$q['id'] === $selectedQcId) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($q['qc_no']) ?> (<?= htmlspecialchars($q['program_no'] ?? 'PRG-20261113-0001') ?> — <?= htmlspecialchars($q['program_name'] ?? 'KIDS - BABY - DRESS') ?>) [25 pcs rem]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Combine with Reworked Checkbox -->
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="combine_rework" id="combineRework">
                            <label class="form-check-label small text-secondary" for="combineRework">
                                Combine with Reworked / Re-inspected Batch
                            </label>
                        </div>

                        <!-- Program & Product Summary Box -->
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.75rem;">Program & Product</div>
                            <div class="fw-bold text-dark mb-3" style="font-size:0.95rem;">
                                <?= htmlspecialchars($programStats['program_no']) ?> — <?= htmlspecialchars($programStats['program_name']) ?>
                            </div>

                            <div class="row g-2 text-center">
                                <div class="col-3">
                                    <div class="bg-white p-2 rounded border">
                                        <div class="text-muted" style="font-size:0.7rem;">Total Passed</div>
                                        <div class="fw-bold text-dark fs-6" id="statTotalPassed"><?= (int)$programStats['total_passed'] ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white p-2 rounded border">
                                        <div class="text-muted" style="font-size:0.7rem;">Packed</div>
                                        <div class="fw-bold text-dark fs-6" id="statPacked"><?= (int)$programStats['packed'] ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white p-2 rounded border">
                                        <div class="text-muted" style="font-size:0.7rem;">Passed Rem.</div>
                                        <div class="fw-bold text-success fs-6" id="statPassedRem"><?= (int)$programStats['passed_rem'] ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white p-2 rounded border">
                                        <div class="text-muted" style="font-size:0.7rem;">Rework</div>
                                        <div class="fw-bold text-warning fs-6" id="statRework"><?= (int)$programStats['rework'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Destination Warehouse -->
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-secondary">
                                Destination Warehouse <span class="text-danger">*</span>
                            </label>
                            <select name="warehouse_name" id="destWarehouse" class="form-select form-select-sm" required>
                                <option value="">-- Select Warehouse --</option>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= htmlspecialchars($wh['name']) ?>" selected>
                                        <?= htmlspecialchars($wh['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Total Box / Carton Count -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-secondary">
                                Total Box / Carton Count <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="total_boxes" id="totalBoxes" class="form-control form-control-sm" value="1" min="1" required>
                            <div class="form-text small text-muted">Total number of boxes or cartons packed for this batch.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Packed Quantities -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-grid-3x3 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark">Packed Quantities</h6>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm fw-semibold">
                                <i class="bi bi-receipt me-1"></i> Bill Generation
                            </button>
                            <span class="badge bg-success px-2.5 py-1.5 fw-bold" id="badgeHeaderPacked">
                                <?= (int)$variant['passed_avail'] ?> pcs Packed
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <!-- Admin Rework Option Toggle -->
                        <div class="p-2.5 rounded-3 mb-4 d-flex justify-content-between align-items-center" style="background: #fffbeb; border: 1px solid #fef08a;">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="rework_included" id="reworkToggle" onchange="toggleReworkOption(this)">
                                <label class="form-check-label small fw-semibold text-dark" for="reworkToggle">
                                    Include Reworked / Repaired Garments in this Batch (Admin Option)
                                </label>
                            </div>
                            <span class="badge bg-warning text-dark px-2 py-1 fw-bold" style="font-size:0.75rem;">
                                9 Rework Pcs in this QC Batch
                            </span>
                        </div>

                        <!-- Variant Packing Table -->
                        <div class="table-responsive">
                            <table class="table align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light text-muted text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                                    <tr>
                                        <th style="width: 20%;">VARIANT</th>
                                        <th style="width: 25%;">QC STATUS & REMAINING</th>
                                        <th style="width: 40%;">APPROVED QR CODE(S) TO PACK</th>
                                        <th style="width: 15%;" class="text-end">QUANTITY TO PACK *</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="align-top py-3">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($variant['size']) ?> / <?= htmlspecialchars($variant['color']) ?></div>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($variant['sku_code']) ?></small>
                                        </td>
                                        <td class="align-top py-3">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-semibold mb-1 d-inline-block">
                                                <?= (int)$variant['passed_avail'] ?> pcs Passed Available
                                            </span>
                                            <div class="small text-muted" style="font-size:0.75rem;">
                                                Total Passed: <?= (int)$variant['total_passed'] ?> pcs (<?= (int)$variant['already_packed'] ?> already packed)
                                            </div>
                                        </td>
                                        <td class="align-top py-3">
                                            <!-- Approved & Excluded Info -->
                                            <div class="small text-success fw-semibold mb-0.5 d-flex align-items-center gap-1">
                                                <i class="bi bi-check-circle-fill"></i> 25 approved QR code(s) available
                                            </div>
                                            <div class="small text-danger mb-2" style="font-size:0.72rem; line-height: 1.3;">
                                                5 rejected QR code(s) excluded. Re-inspect and pass them in QC before packing.
                                            </div>

                                            <!-- QR Code Search Bar -->
                                            <div class="input-group input-group-sm mb-2">
                                                <input type="text" id="qrSearchInput" class="form-control form-control-sm" placeholder="Search QR codes..." onkeyup="filterQrCheckboxes()">
                                            </div>

                                            <!-- Selection Counter & Buttons -->
                                            <div class="d-flex justify-content-between align-items-center mb-2 small">
                                                <span class="text-muted fw-semibold">
                                                    <span id="selectedCount">25</span> selected / 25 qty
                                                </span>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:0.72rem;" onclick="selectAllQr(true)">Select All</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;" onclick="selectAllQr(false)">Clear</button>
                                                </div>
                                            </div>

                                            <!-- Scrollable QR Code Checkbox List -->
                                            <div class="border rounded p-2 bg-light overflow-auto" id="qrListContainer" style="max-height: 160px;">
                                                <div class="row g-1" id="qrCheckboxesRow">
                                                    <?php foreach ($approvedQrTags as $item): ?>
                                                        <div class="col-6 qr-item-col" data-code="<?= htmlspecialchars($item['tag']) ?>">
                                                            <div class="form-check small mb-1">
                                                                <input class="form-check-input qr-checkbox" type="checkbox" name="selected_tags[]" value="<?= htmlspecialchars($item['tag']) ?>" id="qr_<?= htmlspecialchars($item['tag']) ?>" checked onchange="updateQrSelection()">
                                                                <label class="form-check-label font-monospace" for="qr_<?= htmlspecialchars($item['tag']) ?>">
                                                                    <?= htmlspecialchars($item['tag']) ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="form-text small text-muted" style="font-size: 0.72rem;">
                                                Check QR codes to pack. The quantity updates to match your selection.
                                            </div>
                                        </td>
                                        <td class="align-top py-3 text-end">
                                            <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                                <input type="number" name="quantity_to_pack" id="qtyToPack" class="form-control form-control-sm text-end fw-bold" style="width: 75px;" value="25" min="1" max="25" required oninput="handleManualQtyChange(this)">
                                                <span class="small text-muted">pcs</span>
                                            </div>
                                            <div class="small text-muted mt-1" style="font-size:0.72rem;">Max: 25 pcs</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Horizontal Invoice & Financial Summary Bar -->
                        <div class="p-3 bg-light rounded-3 border mt-4">
                            <div class="row g-3 text-center align-items-center">
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5" style="font-size: 0.72rem;">
                                        <i class="bi bi-box me-1"></i> PRODUCT COUNT
                                    </div>
                                    <div class="fw-bold text-dark fs-5" id="footerCount">25 pcs</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5" style="font-size: 0.72rem;">
                                        <i class="bi bi-currency-rupee me-0.5"></i> TAXABLE VALUE
                                    </div>
                                    <div class="fw-bold text-dark fs-5">₹<span id="footerTaxable">16,666.67</span></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5" style="font-size: 0.72rem;">
                                        <i class="bi bi-percent me-0.5"></i> % GST AMOUNT (INCL.)
                                    </div>
                                    <div class="fw-bold text-primary fs-5">₹<span id="footerGst">833.33</span></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5" style="font-size: 0.72rem;">
                                        <i class="bi bi-file-earmark-check me-0.5"></i> TOTAL BILL & INVOICE
                                    </div>
                                    <div class="fw-bold text-success fs-5">₹<span id="footerTotal">17,500.00</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Action Buttons -->
                        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4 pt-2">
                            <a href="<?= \App\Core\Controller::url('/production/batches') ?>" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="button" class="btn btn-outline-success btn-sm px-3 fw-semibold" onclick="handleGenerateBillOnly()">
                                <i class="bi bi-receipt me-1"></i> Generate Bill & Invoice
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold shadow-sm" style="background:#4f46e5; border-color:#4f46e5;">
                                <i class="bi bi-box-seam me-1"></i> Save Packed Batch & Generate Bill / Invoice
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
var piecePrice = 700.00; // Rs 700 per pc

function updateQrSelection() {
    var checkboxes = document.querySelectorAll('.qr-checkbox');
    var checkedCount = 0;
    checkboxes.forEach(function(cb) {
        if (cb.checked) checkedCount++;
    });

    document.getElementById('selectedCount').innerText = checkedCount;
    document.getElementById('badgeHeaderPacked').innerText = checkedCount + ' pcs Packed';
    document.getElementById('qtyToPack').value = checkedCount;
    document.getElementById('footerCount').innerText = checkedCount + ' pcs';

    calculateFinancials(checkedCount);
}

function selectAllQr(state) {
    var checkboxes = document.querySelectorAll('.qr-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = state;
    });
    updateQrSelection();
}

function handleManualQtyChange(input) {
    var val = parseInt(input.value) || 0;
    val = Math.max(0, Math.min(25, val));
    input.value = val;

    var checkboxes = document.querySelectorAll('.qr-checkbox');
    checkboxes.forEach(function(cb, idx) {
        cb.checked = (idx < val);
    });

    document.getElementById('selectedCount').innerText = val;
    document.getElementById('badgeHeaderPacked').innerText = val + ' pcs Packed';
    document.getElementById('footerCount').innerText = val + ' pcs';

    calculateFinancials(val);
}

function calculateFinancials(qty) {
    var totalAmount = qty * piecePrice; // e.g. 25 * 700 = 17500.00
    var gstRate = 0.05; // 5% GST included
    var taxable = totalAmount / (1 + gstRate); // 16666.67
    var gst = totalAmount - taxable; // 833.33

    document.getElementById('footerTaxable').innerText = taxable.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('footerGst').innerText = gst.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('footerTotal').innerText = totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    document.getElementById('inputTaxable').value = taxable.toFixed(2);
    document.getElementById('inputGst').value = gst.toFixed(2);
    document.getElementById('inputTotal').value = totalAmount.toFixed(2);
}

function filterQrCheckboxes() {
    var query = document.getElementById('qrSearchInput').value.toLowerCase();
    var cols = document.querySelectorAll('.qr-item-col');
    cols.forEach(function(col) {
        var code = col.getAttribute('data-code').toLowerCase();
        if (code.includes(query)) {
            col.style.display = '';
        } else {
            col.style.display = 'none';
        }
    });
}

function toggleReworkOption(toggle) {
    if (toggle.checked) {
        alert("Reworked / Repaired garments will be bundled into this dispatch batch.");
    }
}

function handleGenerateBillOnly() {
    alert("Bill & Invoice Draft calculated successfully! Click 'Save Packed Batch & Generate Bill / Invoice' to save.");
}

function validateBatchForm(event) {
    var qty = parseInt(document.getElementById('qtyToPack').value) || 0;
    var wh = document.getElementById('destWarehouse').value;
    var boxes = parseInt(document.getElementById('totalBoxes').value) || 0;

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
    if (qty <= 0) {
        alert("Please select at least 1 QR code piece to pack.");
        event.preventDefault();
        return false;
    }
    return true;
}
</script>
