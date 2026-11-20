<?php
// app/views/concern/batch_create.php
// Pack Finished Production Batch View (Matches Image 1: /production/batches/create)

$initialCount = count($approvedQrTags ?? []);
$unitPrice = (float)($variant['price'] ?: 700.00);
$initialTotal = $initialCount * $unitPrice;
$initialTaxable = $initialTotal > 0 ? round($initialTotal / 1.05, 2) : 0.00;
$initialGst = round($initialTotal - $initialTaxable, 2);
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

    <form action="<?= \App\Core\Controller::url('/production/batches/store') ?>" method="POST" id="formPackBatch" onsubmit="return validateBatchForm(event)">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="program_no" value="<?= htmlspecialchars($programStats['program_no'] ?? 'PRG-GEN') ?>">
        <input type="hidden" name="variant_id" value="<?= (int)($variant['id'] ?? 0) ?>">
        <input type="hidden" name="taxable_value" id="inputTaxable" value="<?= number_format($initialTaxable, 2, '.', '') ?>">
        <input type="hidden" name="gst_amount" id="inputGst" value="<?= number_format($initialGst, 2, '.', '') ?>">
        <input type="hidden" name="total_amount" id="inputTotal" value="<?= number_format($initialTotal, 2, '.', '') ?>">

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
                                        <?= htmlspecialchars($q['qc_no']) ?> (<?= htmlspecialchars($q['program_no'] ?? 'PRG-20261113-0001') ?> — <?= htmlspecialchars($q['program_name'] ?? 'KIDS - BABY - DRESS') ?>) [<?= (int)($q['passed_rem'] ?? $q['passed_qty']) ?> pcs rem]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Combine with Reworked Checkbox -->
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="combine_rework" id="combineRework" onchange="handleCombineRework(this)">
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
                                        <div class="fw-bold fs-6" id="statPacked" style="color: #ef4444;"><?= (int)$programStats['packed'] ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white p-2 rounded border">
                                        <div class="text-muted" style="font-size:0.7rem;">Passed Rem.</div>
                                        <div class="fw-bold fs-6" id="statPassedRem" style="color: #10b981;"><?= (int)$programStats['passed_rem'] ?></div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white p-2 rounded border">
                                        <div class="text-muted" style="font-size:0.7rem;">Rework</div>
                                        <div class="fw-bold fs-6" id="statRework" style="color: #f59e0b;"><?= (int)$programStats['rework'] ?></div>
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
                                <option value="" disabled selected>-- Select Warehouse --</option>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= htmlspecialchars($wh['name']) ?>">
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
                            <button type="button" class="btn btn-outline-success btn-sm fw-semibold" onclick="openBillPreviewModal()">
                                <i class="bi bi-receipt me-1"></i> Bill Generation
                            </button>
                            <span class="badge px-2.5 py-1.5 fw-bold" id="badgeHeaderPacked" style="background:#10b981; color:#fff;">
                                <?= $initialCount ?> pcs Packed
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <!-- Admin Rework Option Toggle -->
                        <div class="p-2.5 rounded-3 mb-4 d-flex justify-content-between align-items-center" style="background: #fffbeb; border: 1px solid #fef08a;">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="rework_included" id="reworkToggle" onchange="toggleReworkOption(this)">
                                <label class="form-check-label small fw-semibold text-dark" for="reworkToggle">
                                    <i class="bi bi-tools text-warning me-1"></i> Include Reworked / Repaired Garments in this Batch (Admin Option)
                                </label>
                            </div>
                            <span class="badge px-2 py-1 fw-bold" id="badgeReworkBanner" style="background:#fef08a; color:#854d0e; font-size:0.75rem;">
                                <?= (int)$programStats['rework'] ?> Rework Pcs in this QC Batch
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
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($variant['size'] ?? 'L') ?> / <?= htmlspecialchars($variant['color'] ?? 'GREEN') ?></div>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($variant['sku_code'] ?? '') ?></small>
                                        </td>
                                        <td class="align-top py-3">
                                            <span class="badge px-2 py-1 fw-semibold mb-1 d-inline-block" style="background:#d1fae5; color:#065f46;">
                                                <i class="bi bi-box me-1"></i> <span id="labelPassedAvail"><?= (int)$variant['passed_avail'] ?></span> pcs Passed Available
                                            </span>
                                            <div class="small text-muted" style="font-size:0.75rem;">
                                                Total Passed: <?= (int)$variant['total_passed'] ?> pcs (<?= (int)$variant['already_packed'] ?> already packed)
                                            </div>
                                        </td>
                                        <td class="align-top py-3">
                                            <!-- Approved & Excluded Info -->
                                            <div class="small text-success fw-semibold mb-0.5 d-flex align-items-center gap-1">
                                                <i class="bi bi-check-circle-fill"></i> <span id="textAvailCount"><?= $initialCount ?></span> approved QR code(s) available
                                            </div>
                                            <?php if ((int)$programStats['rejected'] > 0): ?>
                                                <div class="small text-danger mb-2" style="font-size:0.72rem; line-height: 1.3;">
                                                    <?= (int)$programStats['rejected'] ?> rejected QR code(s) excluded. Re-inspect and pass them in QC before packing.
                                                </div>
                                            <?php endif; ?>

                                            <!-- QR Code Search Bar -->
                                            <div class="input-group input-group-sm mb-2">
                                                <input type="text" id="qrSearchInput" class="form-control form-control-sm" placeholder="Search QR codes..." onkeyup="filterQrCheckboxes()">
                                            </div>

                                            <!-- Selection Counter & Buttons -->
                                            <div class="d-flex justify-content-between align-items-center mb-2 small">
                                                <span class="text-muted fw-semibold">
                                                    <span id="selectedCount"><?= $initialCount ?></span> selected / <span id="totalAvailable"><?= $initialCount ?></span> qty
                                                </span>
                                                <div class="d-flex align-items-center">
                                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2" style="font-size:0.75rem;" onclick="selectAllQr(true)">
                                                        <i class="bi bi-check2 me-1"></i>Select All
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 ms-1" style="font-size:0.75rem;" onclick="selectAllQr(false)">
                                                        Clear
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Scrollable QR Code Checkbox List (Single Column in Image 1) -->
                                            <div class="border rounded p-2 bg-light overflow-auto" id="qrListContainer" style="max-height: 180px;">
                                                <div class="d-flex flex-column gap-1" id="qrCheckboxesRow">
                                                    <?php foreach ($approvedQrTags as $item): ?>
                                                        <div class="form-check small mb-1 qr-item-col" data-code="<?= htmlspecialchars($item['tag']) ?>">
                                                            <input class="form-check-input qr-checkbox" type="checkbox" name="selected_tags[]" value="<?= htmlspecialchars($item['tag']) ?>" id="qr_<?= htmlspecialchars($item['tag']) ?>" checked onchange="updateQrSelection()">
                                                            <label class="form-check-label font-monospace fw-semibold text-dark" for="qr_<?= htmlspecialchars($item['tag']) ?>">
                                                                <?= htmlspecialchars($item['tag']) ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="form-text small text-muted mt-1" style="font-size: 0.72rem;">
                                                Check QR codes to pack. The quantity updates to match your selection.
                                            </div>
                                        </td>
                                        <td class="align-top py-3 text-end">
                                            <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                                <input type="number" name="quantity_to_pack" id="qtyToPack" class="form-control form-control-sm text-end fw-bold" style="width: 75px;" value="<?= $initialCount ?>" min="1" max="<?= $initialCount ?>" required oninput="handleManualQtyChange(this)">
                                                <span class="small text-muted">pcs</span>
                                            </div>
                                            <div class="small text-muted mt-1" id="subtextMax">Max: <?= $initialCount ?> pcs</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Horizontal Invoice & Financial Summary Bar (Image 1) -->
                        <div class="p-3 bg-light rounded-3 border mt-4">
                            <div class="row g-3 text-center align-items-center">
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-box me-1"></i> PRODUCT COUNT
                                    </div>
                                    <div class="fw-bold text-dark fs-5" id="footerCount"><?= $initialCount ?> pcs</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-currency-rupee me-0.5"></i> TAXABLE VALUE
                                    </div>
                                    <div class="fw-bold text-dark fs-5">₹<span id="footerTaxable"><?= number_format($initialTaxable, 2) ?></span></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-percent me-0.5"></i> % GST AMOUNT (INCL.)
                                    </div>
                                    <div class="fw-bold fs-5" style="color: #2563eb;">₹<span id="footerGst"><?= number_format($initialGst, 2) ?></span></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="small text-muted text-uppercase mb-0.5 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-file-earmark-check me-0.5"></i> TOTAL BILL & INVOICE
                                    </div>
                                    <div class="fw-bold fs-5" style="color: #10b981;">₹<span id="footerTotal"><?= number_format($initialTotal, 2) ?></span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Action Buttons -->
                        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4 pt-2">
                            <a href="<?= \App\Core\Controller::url('/production/batches') ?>" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="button" class="btn btn-outline-success btn-sm px-3 fw-semibold" onclick="openBillPreviewModal()">
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
                    <span class="text-muted small">Product Variant</span>
                    <span class="fw-bold text-dark"><?= htmlspecialchars($variant['name']) ?> (<?= htmlspecialchars($variant['size']) ?>)</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Target Warehouse</span>
                    <span class="fw-semibold text-primary" id="previewWarehouse">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Pieces to Pack</span>
                    <span class="fw-bold fs-6 text-dark" id="previewPieces">0 pcs</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small">Unit Price</span>
                    <span class="fw-semibold text-secondary">₹<?= number_format($unitPrice, 2) ?> / pc</span>
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
                    <i class="bi bi-check2-circle me-1"></i> Confirm & Save Packed Batch
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var piecePrice = <?= json_encode($unitPrice) ?>;
var passedPieces = <?= (int)$initialCount ?>;
var maxPieces = <?= (int)$initialCount ?>;
var reworkTagsList = <?= json_encode($reworkTags ?? []) ?>;

function handleQcChange(select) {
    if (select.value) {
        window.location.href = '<?= \App\Core\Controller::url('/production/batches/create') ?>?qc_batch_id=' + select.value;
    }
}

function handleCombineRework(checkbox) {
    var toggle = document.getElementById('reworkToggle');
    if (toggle) {
        toggle.checked = checkbox.checked;
        toggleReworkOption(toggle);
    }
}

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
        // Only modify if parent row is visible (not filtered out by search)
        var row = cb.closest('.qr-item-col');
        if (!row || row.style.display !== 'none') {
            cb.checked = state;
        }
    });
    updateQrSelection();
}

function handleManualQtyChange(input) {
    var val = parseInt(input.value) || 0;
    val = Math.max(0, Math.min(maxPieces, val));
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
    var totalAmount = qty * piecePrice;
    var gstRate = 0.05; // 5% GST included
    var taxable = totalAmount > 0 ? (totalAmount / (1 + gstRate)) : 0;
    var gst = totalAmount - taxable;

    document.getElementById('footerTaxable').innerText = taxable.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('footerGst').innerText = gst.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('footerTotal').innerText = totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    document.getElementById('inputTaxable').value = taxable.toFixed(2);
    document.getElementById('inputGst').value = gst.toFixed(2);
    document.getElementById('inputTotal').value = totalAmount.toFixed(2);
}

function filterQrCheckboxes() {
    var query = document.getElementById('qrSearchInput').value.toLowerCase().trim();
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
    var container = document.getElementById('qrCheckboxesRow');
    var existingReworkElements = container.querySelectorAll('.rework-qr-item');

    if (toggle.checked) {
        // Add rework tags dynamically if not already added
        if (existingReworkElements.length === 0 && reworkTagsList.length > 0) {
            reworkTagsList.forEach(function(item) {
                var div = document.createElement('div');
                div.className = 'form-check small mb-1 qr-item-col rework-qr-item';
                div.setAttribute('data-code', item.tag);
                div.innerHTML = `
                    <input class="form-check-input qr-checkbox" type="checkbox" name="selected_tags[]" value="${item.tag}" id="qr_${item.tag}" checked onchange="updateQrSelection()">
                    <label class="form-check-label font-monospace fw-semibold text-warning" for="qr_${item.tag}">
                        ${item.tag} <span class="badge bg-warning text-dark py-0 px-1" style="font-size:0.65rem;">REWORK</span>
                    </label>
                `;
                container.appendChild(div);
            });
        }
        maxPieces = passedPieces + reworkTagsList.length;
    } else {
        // Remove rework tags
        existingReworkElements.forEach(function(el) {
            el.remove();
        });
        maxPieces = passedPieces;
    }

    document.getElementById('totalAvailable').innerText = maxPieces;
    document.getElementById('textAvailCount').innerText = maxPieces;
    document.getElementById('qtyToPack').max = maxPieces;
    document.getElementById('subtextMax').innerText = 'Max: ' + maxPieces + ' pcs';
    updateQrSelection();
}

function openBillPreviewModal() {
    var qty = parseInt(document.getElementById('qtyToPack').value) || 0;
    var wh = document.getElementById('destWarehouse').value || 'Main Warehouse';

    document.getElementById('previewWarehouse').innerText = wh;
    document.getElementById('previewPieces').innerText = qty + ' pcs';
    document.getElementById('previewTaxable').innerText = document.getElementById('footerTaxable').innerText;
    document.getElementById('previewGst').innerText = document.getElementById('footerGst').innerText;
    document.getElementById('previewTotal').innerText = document.getElementById('footerTotal').innerText;

    new bootstrap.Modal(document.getElementById('modalBillPreview')).show();
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
