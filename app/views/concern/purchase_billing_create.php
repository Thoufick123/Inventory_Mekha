<?php
$isEdit = !empty($bill);
$currentPaymentMethod = strtolower(str_replace([' ', '-'], '_', trim((string)($bill['payment_method'] ?? 'credit'))));
$paymentMethods = [
    'credit' => 'Credit',
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'upi' => 'UPI',
];
$existingAttachments = [];
if ($isEdit) {
    $decodedAttachments = json_decode((string)($bill['attachment_paths'] ?? ''), true);
    if (is_array($decodedAttachments)) {
        $existingAttachments = array_values(array_filter($decodedAttachments, static function ($path): bool {
            return is_string($path) && trim($path) !== '';
        }));
    }
    if (empty($existingAttachments) && !empty($bill['attachment_path'])) {
        $existingAttachments[] = (string)$bill['attachment_path'];
    }
}
$existingRolls = [];
$existingOther = [];
$existingFinished = [];
if ($isEdit && !empty($existingItems)) {
    foreach ($existingItems as $it) {
        if ($it['section_type'] === 'rolls') {
            $existingRolls[] = $it;
        } elseif ($it['section_type'] === 'garment_other') {
            $existingOther[] = $it;
        } elseif ($it['section_type'] === 'finished_goods') {
            $existingFinished[] = $it;
        }
    }
}
?>
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-2 d-flex align-items-center justify-content-center text-white" style="background: #6366f1; width: 44px; height: 44px;">
                <i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-bag-plus' ?> fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-dark"><?= $isEdit ? ('Edit Purchase Bill: #' . htmlspecialchars($bill['bill_no'])) : 'Record Production Purchase' ?></h4>
                <p class="text-muted small mb-0"><?= $isEdit ? 'Update fabric rolls, garment supplies/accessories and finished goods for this bill.' : 'Record fabric rolls, garment supplies/accessories and finished goods received from vendors.' ?></p>
            </div>
        </div>
        <a href="<?= \App\Core\Controller::url('/m/purchase_billing') ?>" class="btn btn-outline-secondary btn-sm px-3 shadow-sm rounded-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Purchases
        </a>
    </div>

    <form action="<?= $isEdit ? \App\Core\Controller::url('/m/purchase_billing/update') : \App\Core\Controller::url('/m/purchase_billing/store') ?>" method="POST" enctype="multipart/form-data" id="formPurchaseBill" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="bill_id" value="<?= (int)$bill['id'] ?>">
        <?php endif; ?>

        <!-- Section 1: Purchase & Supplier Details -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                <i class="bi bi-receipt text-primary me-2 fs-5"></i>
                <h6 class="mb-0 fw-bold text-dark">Purchase & Supplier Details</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <?php if (empty($selectedCid)): ?>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Target Concern <span class="text-danger">*</span></label>
                            <select name="concern_id" id="billConcernId" class="form-select" required>
                                <option value="">-- Choose Concern --</option>
                                <?php foreach ($allConcerns ?? [] as $con): ?>
                                    <option value="<?= $con['id'] ?>" <?= ($isEdit && $bill['concern_id'] == $con['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Target concern is required.</div>
                        </div>
                    <?php endif; ?>

                    <div class="<?= empty($selectedCid) ? 'col-md-3' : 'col-md-4' ?>">
                        <label class="form-label small fw-semibold">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplierSelect" class="form-select" required>
                            <option value="">-- Select registered supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>" data-district="<?= htmlspecialchars($sup['district'] ?? '') ?>" <?= ($isEdit && $bill['supplier_id'] == $sup['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sup['name']) ?> <?= !empty($sup['company_name']) ? ('(' . htmlspecialchars($sup['company_name']) . ')') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please choose a supplier.</div>
                    </div>

                    <div class="<?= empty($selectedCid) ? 'col-md-3' : 'col-md-4' ?>">
                        <label class="form-label small fw-semibold">Purchase Date <span class="text-danger">*</span></label>
                        <input type="date" name="bill_date" class="form-control" value="<?= htmlspecialchars($isEdit ? $bill['bill_date'] : date('Y-m-d')) ?>" required>
                        <div class="invalid-feedback">Valid purchase date is required.</div>
                    </div>

                    <div class="<?= empty($selectedCid) ? 'col-md-3' : 'col-md-4' ?>">
                        <label class="form-label small fw-semibold">Invoice / Bill Number <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_invoice_no" id="billSupplierInvoiceNo" class="form-control" placeholder="e.g. INV-2026-8891" value="<?= htmlspecialchars($isEdit ? ($bill['supplier_invoice_no'] ?? '') : '') ?>" required>
                        <div class="invalid-feedback">Invoice / Bill number is required.</div>
                    </div>
                </div>

                <!-- Payment Method & Inward Settlement Details -->
                <div class="row g-3 mb-3 p-3 rounded-3 bg-light border">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark">Payment Status</label>
                        <select name="payment_status" id="billPaymentStatus" class="form-select form-select-sm">
                            <option value="unpaid" <?= ($bill['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid (Awaiting settlement)</option>
                            <option value="partial" <?= ($bill['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>>Partial (Partially paid)</option>
                            <option value="paid" <?= ($bill['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid (Fully settled)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark">Payment Method</label>
                        <select name="payment_method" id="billPaymentMethod" class="form-select form-select-sm">
                            <?php if (!isset($paymentMethods[$currentPaymentMethod]) && !empty($bill['payment_method'])): ?>
                                <option value="<?= htmlspecialchars($bill['payment_method']) ?>" selected>
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $bill['payment_method']))) ?> (Existing)
                                </option>
                            <?php endif; ?>
                            <?php foreach ($paymentMethods as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $currentPaymentMethod === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark">Payment Ref. / Cheque / UTR</label>
                        <input type="text" name="payment_reference" id="billPaymentRef" class="form-control form-control-sm" placeholder="e.g. UTR-998812 / CHQ-0012" value="<?= htmlspecialchars($bill['payment_reference'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark">Stock Status</label>
                        <div class="form-control form-control-sm bg-white text-muted">Pending - use Label Generation to add stock</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Tax Invoice / Bill Attachment <span class="text-danger">*</span></label>
                        <input type="file" name="attachments[]" id="billAttachments" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple <?= ($isEdit && !empty($existingAttachments)) ? '' : 'required' ?>>
                        <div class="invalid-feedback">Please choose at least one tax invoice / bill attachment file.</div>
                        <div class="form-text text-muted small">Select multiple JPG, PNG, WEBP or PDF files. Each file can be up to 5 MB.</div>
                        <?php if (!empty($existingAttachments)): ?>
                            <div class="mt-1 small">
                                <span class="text-muted">Current attachments:</span>
                                <ul class="mb-0">
                                    <?php foreach ($existingAttachments as $index => $attachmentPath): ?>
                                        <li>
                                            <a href="<?= htmlspecialchars(\App\Services\CloudinaryService::url($attachmentPath)) ?>" target="_blank" rel="noopener" class="fw-semibold text-primary">
                                                <i class="bi bi-paperclip"></i> Attachment <?= $index + 1 ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Purchase Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Consignment reference, gate pass, or vehicle number" value="<?= htmlspecialchars($isEdit ? ($bill['notes'] ?? '') : '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Items in a Single Row with One-by-One Sections -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <!-- Single Row Navigation Header -->
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <ul class="nav nav-pills p-1 bg-light rounded-3 gap-1" id="purchaseSectionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active px-3 py-2 fw-semibold d-flex align-items-center gap-2" id="tabRollsBtn" data-bs-toggle="pill" data-bs-target="#paneRolls" type="button" role="tab">
                                <i class="bi bi-layers text-primary"></i> Individual Fabric Rolls
                                <span class="badge bg-primary rounded-pill" id="rollBadgeCount">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-2 fw-semibold d-flex align-items-center gap-2" id="tabOtherBtn" data-bs-toggle="pill" data-bs-target="#paneOther" type="button" role="tab">
                                <i class="bi bi-box-seam text-success"></i> Other Garment Supplies
                                <span class="badge bg-success rounded-pill" id="otherBadgeCount">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-2 fw-semibold d-flex align-items-center gap-2" id="tabFinishedBtn" data-bs-toggle="pill" data-bs-target="#paneFinished" type="button" role="tab">
                                <i class="bi bi-tag text-purple" style="color: #8b5cf6;"></i> Finished Goods
                                <span class="badge bg-secondary rounded-pill" id="finishedBadgeCount">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-2 fw-semibold d-flex align-items-center gap-2 text-dark" id="tabAllBtn" data-bs-toggle="pill" data-bs-target="#paneAll" type="button" role="tab">
                                <i class="bi bi-grid-3x3-gap"></i> All Sections Overview
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Tab Panes: One-by-One Sections -->
            <div class="card-body p-3 p-md-4">
                <div id="itemsValidationError" class="alert alert-danger py-2 small d-none mb-3" role="alert">
                    Add at least one item with all required details before saving this purchase.
                </div>
                <div class="tab-content" id="purchaseSectionContent">

                    <!-- ========================================== -->
                    <!-- 1. INDIVIDUAL FABRIC ROLLS (EXACT IMAGE 1) -->
                    <!-- ========================================== -->
                    <div class="tab-pane fade show active" id="paneRolls" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-layers text-primary me-1"></i> Individual Fabric Rolls (<span id="rollCountHeader">0</span>)</h6>
                                <small class="text-muted">Add each roll received on this supplier invoice.</small>
                            </div>
                            <button type="button" class="btn btn-sm text-white px-3 fw-semibold shadow-sm" style="background: #4f46e5;" onclick="addRollRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Fabric Roll
                            </button>
                        </div>

                        <!-- Autocomplete Datalists for Color and Design Patterns -->
                        <datalist id="rollColorList">
                            <?php foreach ($savedColors ?? [] as $sc): ?>
                                <option value="<?= htmlspecialchars($sc['color']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <datalist id="rollPatternList">
                            <?php foreach ($savedDesigns ?? [] as $sd): ?>
                                <option value="<?= htmlspecialchars($sd['design_name']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <!-- Dynamic Roll Input Cards (Starts Empty) -->
                        <div id="rollsContainer"></div>

                        <div id="noRollsPlaceholder" class="p-4 bg-light rounded-3 text-center text-muted mb-3">
                            <i class="bi bi-layers fs-3 d-block mb-1 text-secondary"></i>
                            No fabric rolls added. Click <strong>+ Add Fabric Roll</strong> to add roll items.
                        </div>

                        <!-- Fabric Rolls Inventory & Details Table (Matching Image 1 & 2) -->
                        <div class="card border mb-3 shadow-none rounded-3">
                            <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center">
                                <i class="bi bi-table text-primary me-2"></i>
                                <span class="fw-bold small text-dark">Fabric Rolls Inventory & Details Table</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle mb-0 text-center small" id="rollsInventoryTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th style="width: 60px;">PHOTO</th>
                                            <th>ROLL CODE</th>
                                            <th>HSN</th>
                                            <th>COLOR / SHADE</th>
                                            <th>DESIGN / PATTERN</th>
                                            <th>OVERALL WEIGHT (KG)</th>
                                            <th>UNIT PRICE (₹)</th>
                                            <th>QTY</th>
                                            <th>TOTAL</th>
                                            <th>GST TYPE</th>
                                            <th>TAX</th>
                                            <th class="table-success text-success fw-bold">TOTAL VALUE (TAX+TOTAL)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rollsInventoryTableBody">
                                        <tr><td colspan="13" class="text-center py-3 text-muted">Add fabric rolls to see inventory details.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Batch Metrics Summary (Matching Image 1 & 2) -->
                        <div class="p-3 rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-3 shadow-none" style="background: #eef2ff; border: 1px solid #c7d2fe;">
                            <div>
                                <div class="fw-bold text-primary mb-0"><i class="bi bi-calculator me-1"></i> Batch Metrics Summary</div>
                                <small class="text-muted">Totals are calculated from the rolls in this purchase.</small>
                            </div>
                            <div class="d-flex flex-wrap gap-4 text-center">
                                <div>
                                    <small class="text-muted text-uppercase d-block fw-semibold">Total Rolls</small>
                                    <span class="fw-bold text-dark fs-6" id="metricRolls">1 Roll</span>
                                </div>
                                <div>
                                    <small class="text-muted text-uppercase d-block fw-semibold">Total Qty</small>
                                    <span class="fw-bold text-dark fs-6" id="metricRq">1</span>
                                </div>
                                <div>
                                    <small class="text-muted text-uppercase d-block fw-semibold">Colors / Shades</small>
                                    <span class="fw-bold text-dark fs-6" id="metricColorsDesigns">0 Colors</span>
                                </div>
                                <div>
                                    <small class="text-muted text-uppercase d-block fw-semibold">Total Weight</small>
                                    <span class="fw-bold text-primary fs-6" id="metricWeight">0.00 kg</span>
                                </div>
                                <div>
                                    <small class="text-muted text-uppercase d-block fw-semibold">Total incl. Tax</small>
                                    <span class="fw-bold text-success fs-5" id="metricTotalInclTax">₹0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 2. OTHER GARMENT SUPPLIES (EXACT IMAGE 2)      -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="paneOther" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam text-success me-1"></i> Other Garment Supplies</h6>
                                <small class="text-muted">Optional non-fabric products received on this invoice.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary px-3 fw-semibold shadow-sm" onclick="addOtherRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Supply
                            </button>
                        </div>

                        <!-- Dynamic Supply Input Cards (Starts Empty) -->
                        <div id="garmentOtherContainer"></div>

                        <div id="noSuppliesPlaceholder" class="p-4 bg-light rounded-3 text-center text-muted mb-3">
                            <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                            No general supplies added. Click <strong>+ Add Supply</strong> to add buttons, threads, zippers or trims.
                        </div>

                        <!-- Other Garment Supplies Details Table (Matching Image 2) -->
                        <div class="card border mb-3 shadow-none rounded-3">
                            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-table text-success me-2"></i>
                                    <span class="fw-bold small text-dark">Other Garment Supplies Details Table</span>
                                </div>
                                <span class="badge bg-light text-dark border font-monospace" id="otherItemsCountBadge">0 Items</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle mb-0 text-center small" id="otherSuppliesTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th>OTHER PRODUCT NO/CODE</th>
                                            <th>ITEM NAME / DESCRIPTION</th>
                                            <th>HSN</th>
                                            <th>QUANTITY</th>
                                            <th>UNIT</th>
                                            <th>UNIT PRICE</th>
                                            <th>BASE VALUE</th>
                                            <th>GST TYPE</th>
                                            <th>TAX</th>
                                            <th class="table-success text-success fw-bold">TOTAL VALUE</th>
                                        </tr>
                                    </thead>
                                    <tbody id="otherSuppliesTableBody">
                                        <tr><td colspan="11" class="text-center py-3 text-muted">Add garment supplies to see their purchase details.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 3. FINISHED GOODS (BASED ON REFERENCE IMAGE 3) -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="paneFinished" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark" style="color: #6d28d9;"><i class="bi bi-tag text-purple me-1"></i> Finished Goods / Apparel Items</h6>
                                <small class="text-muted">Record complete garments, t-shirts, shirts or series packs received into inventory.</small>
                            </div>
                            <button type="button" class="btn btn-sm text-white px-3 fw-semibold shadow-sm" style="background: #8b5cf6;" onclick="addFinishedRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Finished Good
                            </button>
                        </div>

                        <!-- Dynamic Finished Goods Input Cards (Starts Empty) -->
                        <div id="finishedGoodsContainer"></div>

                        <div id="noFinishedPlaceholder" class="p-4 bg-light rounded-3 text-center text-muted mb-3">
                            <i class="bi bi-tag fs-3 d-block mb-1 text-secondary"></i>
                            No finished goods added. Click <strong>+ Add Finished Good</strong> to add ready apparel.
                        </div>

                        <!-- Finished Goods Inventory & Details Table (Matching Image 3) -->
                        <div class="card border mb-3 shadow-none rounded-3">
                            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-table text-purple me-2" style="color:#8b5cf6;"></i>
                                    <span class="fw-bold small text-dark">Finished Goods Inventory & Details Table</span>
                                </div>
                                <span class="badge bg-light text-dark border font-monospace" id="finishedItemsCountBadge">0 Items</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle mb-0 text-center small" id="finishedGoodsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th>PRODUCT</th>
                                            <th>HSN</th>
                                            <th>QTY</th>
                                            <th>UNIT PRICE OF SUPPLIER COST (₹)</th>
                                            <th>GST TYPE</th>
                                            <th>TAX</th>
                                            <th class="table-success text-success fw-bold">TOTAL VALUE</th>
                                        </tr>
                                    </thead>
                                    <tbody id="finishedGoodsTableBody">
                                        <tr><td colspan="8" class="text-center py-3 text-muted">Add finished goods to see inventory details.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 4. ALL SECTIONS OVERVIEW                       -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="paneAll" role="tabpanel">
                        <div class="alert alert-light border small text-muted mb-4 d-flex align-items-center">
                            <i class="bi bi-info-circle me-2 text-primary fs-5"></i>
                            <span>Quick overview of all 3 purchase categories. Click any card to navigate directly into that section.</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card border p-3 rounded-3 text-center bg-light h-100">
                                    <div class="fw-bold text-primary mb-1"><i class="bi bi-layers me-1"></i> Fabric Rolls</div>
                                    <div class="fs-4 fw-bold text-dark my-1" id="overviewRollsVal">₹0.00</div>
                                    <div class="small text-muted mb-3"><span id="overviewRollsCount">0</span> roll(s) &bull; <span id="overviewRollsWeight">0.00</span> kg</div>
                                    <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="document.getElementById('tabRollsBtn').click();">
                                        Open Rolls Section
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border p-3 rounded-3 text-center bg-light h-100">
                                    <div class="fw-bold text-success mb-1"><i class="bi bi-box-seam me-1"></i> Other Garment Supplies</div>
                                    <div class="fs-4 fw-bold text-dark my-1" id="overviewOtherVal">₹0.00</div>
                                    <div class="small text-muted mb-3"><span id="overviewOtherCount">0</span> supply item(s)</div>
                                    <button type="button" class="btn btn-sm btn-outline-success w-100" onclick="document.getElementById('tabOtherBtn').click();">
                                        Open Supplies Section
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border p-3 rounded-3 text-center bg-light h-100">
                                    <div class="fw-bold text-purple mb-1" style="color:#8b5cf6;"><i class="bi bi-tag me-1"></i> Finished Goods</div>
                                    <div class="fs-4 fw-bold text-dark my-1" id="overviewFinishedVal">₹0.00</div>
                                    <div class="small text-muted mb-3"><span id="overviewFinishedCount">0</span> finished style(s)</div>
                                    <button type="button" class="btn btn-sm text-white w-100" style="background:#8b5cf6;" onclick="document.getElementById('tabFinishedBtn').click();">
                                        Open Finished Goods Section
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Section 3: Summary & Total Price Card (Exact Image 2 Bottom Layout) -->
        <div class="card border-0 shadow-sm rounded-3 mb-5 p-4 bg-white">
            <div class="row align-items-center justify-content-end">
                <div class="col-md-5 col-lg-4 text-end">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary fw-semibold">Subtotal</span>
                        <span class="fw-bold text-dark fs-6" id="summarySubtotal">₹0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary fw-semibold">Taxes</span>
                        <span class="fw-bold text-dark fs-6" id="summaryTaxes">₹0.00</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold text-dark fs-5">Total Price</span>
                        <span class="fw-bold fs-3" style="color: #16a34a;" id="summaryTotalPrice">₹0.00</span>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <a href="<?= \App\Core\Controller::url('/m/purchase_billing') ?>" class="btn btn-light border px-4 py-2 fw-semibold text-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn text-white px-4 py-2 fw-semibold shadow-sm rounded-2 d-flex align-items-center gap-2" style="background: #4f46e5;" id="btnSavePurchase">
                            <i class="bi bi-check-circle fs-6"></i> <?= $isEdit ? 'Update Purchase Bill' : 'Save Purchase' ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- ============================================================== -->
<!-- Add New HSN Code & GST Rate Modal (EXACTLY AS IN IMAGE 1)     -->
<!-- ============================================================== -->
<div class="modal fade" id="modalQuickHsn" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <!-- Modal Header (Blue header with icon and white close button) -->
            <div class="modal-header text-white px-4 py-3" style="background: #2563eb;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0">Add HSN Code & GST Details</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- HSN Code -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">HSN Code <span class="text-danger">*</span></label>
                    <input type="text" id="newHsnCode" class="form-control text-uppercase font-monospace" placeholder="E.G. 6206 OR 61091000" data-hsn-autocomplete data-hsn-type="HSN" data-hsn-description-target="#newHsnDesc" data-hsn-gst-target="#newHsnGstRate" data-hsn-note-target="#newHsnRateNote" autocomplete="off" required>
                    <div class="form-text text-muted small mt-1">Harmonized System of Nomenclature code for invoices</div>
                </div>

                <!-- Commodity / Description -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Commodity / Description <span class="text-danger">*</span></label>
                    <input type="text" id="newHsnDesc" class="form-control" placeholder="e.g. Cotton Woven Fabric / Sewing Thread / Buttons" required>
                </div>

                <!-- Default GST Rate (%) -->
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark">GST Rate (%) (Optional)</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" max="100" id="newHsnGstRate" class="form-control fw-semibold" placeholder="0.00">
                        <span class="input-group-text bg-light text-muted fw-bold">%</span>
                    </div>
                    <div class="form-text text-muted small mt-1">Leave blank when the rate depends on item details or price.</div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark">GST Rate Conditions / Notes (Optional)</label>
                    <input type="text" id="newHsnRateNote" class="form-control" maxlength="500" placeholder="e.g. Verify rate based on product composition and value">
                </div>
            </div>
            <!-- Modal Footer (Cancel button + Purple Save & Select HSN) -->
            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn text-white px-4 fw-semibold shadow-sm rounded-2 d-flex align-items-center gap-2" style="background: #4f46e5;" onclick="saveAndSelectHsn()">
                    <i class="bi bi-check-circle fs-6"></i> Save & Select HSN
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Modal: Add New Unit (Matching Image 3 with Add Option) -->
<div class="modal fade" id="modalQuickUnit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header text-white px-4 py-3" style="background: #2563eb;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0">Add New Unit of Measurement</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Unit Name <span class="text-danger">*</span></label>
                    <input type="text" id="newUnitName" class="form-control" placeholder="e.g. Cones, Dozens, Rolls, Bags" required>
                    <div class="form-text text-muted small">This unit will be instantly added to all unit dropdowns across the page.</div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4 fw-semibold shadow-sm" onclick="saveAndSelectUnit()">
                    <i class="bi bi-check-circle me-1"></i> Save & Select Unit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Register New Roll Color & Design (Create Option) -->
<div class="modal fade" id="modalCreateRollDesign" tabindex="-1" aria-labelledby="modalCreateRollDesignLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header text-white px-4 py-3" style="background: #2563eb;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle-fill fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0" id="modalCreateRollDesignLabel">Register New Roll Color & Design</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-primary py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                    <div>
                        Add a new design pattern & color to your concern registry. It will be immediately saved and available for all purchase and stock workflows.
                    </div>
                </div>

                <form id="formCreateRollDesign" onsubmit="event.preventDefault(); saveAndApplyRollDesign();">
                    <input type="hidden" id="newDesignTargetRow" value="1">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">Design Name / Pattern <span class="text-danger">*</span></label>
                            <input type="text" id="newDesignPattern" class="form-control text-uppercase" placeholder="E.G. FLORAL 01, CHECKS" required list="rollPatternList">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">Color / Shade <span class="text-danger">*</span></label>
                            <input type="text" id="newDesignColor" class="form-control text-uppercase" placeholder="E.G. NAVY BLUE, RED" required list="rollColorList">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary mb-1">Standard / Unit Price (₹)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" id="newDesignPrice" class="form-control" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary mb-1">HSN Code</label>
                            <input type="text" id="newDesignHsn" class="form-control text-uppercase form-control-sm" placeholder="E.G. 6543, 5208" data-hsn-autocomplete data-hsn-type="HSN" autocomplete="off">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnSubmitRollDesign">
                            <i class="bi bi-check-circle me-1"></i> Save & Apply to Roll
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: View Saved Color & Design Registry (View Option) -->
<div class="modal fade" id="modalViewRollSpecs" tabindex="-1" aria-labelledby="modalViewRollSpecsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-dark text-white px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-eye-fill text-info fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0" id="modalViewRollSpecsLabel">Roll Color & Design Specification Viewer</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Single Spec Highlight View (when viewing selected spec) -->
                <div id="viewSpecDetailCard" class="card border-info mb-3 bg-light shadow-sm" style="display: none;">
                    <div class="card-body p-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <span class="badge bg-primary text-uppercase px-2.5 py-1.5 fs-6 me-2" id="vSpecPatternBadge">PATTERN</span>
                                <span class="badge bg-dark text-uppercase px-2.5 py-1.5 fs-6" id="vSpecColorBadge">COLOR</span>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> Selected Specification
                            </span>
                        </div>
                        <div class="row g-2 text-center mb-3">
                            <div class="col-sm-3 col-6">
                                <div class="p-2 bg-white rounded border">
                                    <div class="text-muted small">Available Rolls</div>
                                    <div class="fs-5 fw-bold text-primary" id="vSpecQty">0.00</div>
                                </div>
                            </div>
                            <div class="col-sm-3 col-6">
                                <div class="p-2 bg-white rounded border">
                                    <div class="text-muted small">Net Weight</div>
                                    <div class="fs-5 fw-bold text-dark" id="vSpecWeight">0.00 kg</div>
                                </div>
                            </div>
                            <div class="col-sm-3 col-6">
                                <div class="p-2 bg-white rounded border">
                                    <div class="text-muted small">Last Unit Price</div>
                                    <div class="fs-5 fw-bold text-success" id="vSpecPrice">₹0.00</div>
                                </div>
                            </div>
                            <div class="col-sm-3 col-6">
                                <div class="p-2 bg-white rounded border">
                                    <div class="text-muted small">HSN Code</div>
                                    <div class="fs-5 fw-bold text-secondary" id="vSpecHsn">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="small text-muted d-flex flex-wrap justify-content-between gap-2 p-2 bg-white rounded border">
                            <div><i class="bi bi-clock-history me-1"></i> Inward Bills: <strong id="vSpecBillsCount">0</strong></div>
                            <div><i class="bi bi-receipt me-1"></i> Last Bill: <strong id="vSpecLastBill">N/A</strong></div>
                            <div><i class="bi bi-building me-1"></i> Supplier: <strong id="vSpecSupplier">N/A</strong></div>
                        </div>
                    </div>
                </div>

                <!-- Directory Search & Table -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                    <h6 class="fw-bold mb-0 text-secondary">
                        <i class="bi bi-table me-1 text-primary"></i> Saved Specifications Directory (<span id="vSpecTotalCount">0</span>)
                    </h6>
                    <div class="input-group input-group-sm" style="max-width: 280px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="vSpecSearchInput" class="form-control border-start-0" placeholder="Filter pattern, color, HSN..." oninput="filterRegistrySpecsTable(this.value)">
                    </div>
                </div>

                <div class="table-responsive rounded border" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" id="tableRegistrySpecs">
                        <thead class="table-light sticky-top">
                            <tr class="small text-uppercase text-secondary">
                                <th>Design / Pattern</th>
                                <th>Color / Shade</th>
                                <th class="text-end">Stock (Rolls)</th>
                                <th class="text-end">Weight (kg)</th>
                                <th class="text-end">Last Price</th>
                                <th>HSN</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyRegistrySpecs" class="small">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCreateDesignModal(targetRollRowIndex)">
                    <i class="bi bi-plus-lg me-1"></i> + Register New Design
                </button>
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const isEditMode = <?= $isEdit ? 'true' : 'false' ?>;
const existingRolls = <?= json_encode($existingRolls) ?>;
const existingOther = <?= json_encode($existingOther) ?>;
const existingFinished = <?= json_encode($existingFinished) ?>;
let savedRollSpecsRegistry = <?= json_encode($savedRollSpecs ?? []) ?>;
const savedColorsRegistry = <?= json_encode($savedColors ?? []) ?>;
const savedDesignsRegistry = <?= json_encode($savedDesigns ?? []) ?>;

let rollCounter = 0;
let otherCounter = 0;
let finishedCounter = 0;
let currentHsnTarget = { type: 'rolls', id: 1 };
let createdHsnList = <?= json_encode($hsnList ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.hsnSearchData = createdHsnList;

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function generateRollCode(num, name = '') {
    const clean = (name || '').trim().toUpperCase().replace(/\s+/g, '-');
    if (clean) {
        return 'ROLL-' + clean;
    }
    return 'ROLL-' + (num < 10 ? '0' + num : num);
}

function updateRollHeaderCode(id) {
    const card = document.getElementById('rollCard_' + id);
    if (!card) return;
    const input = card.querySelector('.roll-input-code');
    const badge = card.querySelector('.roll-code-badge');
    if (badge) {
        badge.textContent = generateRollCode(id, input ? input.value : '');
    }
}

function getHsnInputAttributes(selectedVal = '') {
    const row = findHsnMasterRow(selectedVal);
    return `data-hsn-gst-rate="${escapeHtml(row?.gst_rate ?? '')}" data-hsn-rate-note="${escapeHtml(row?.rate_note || '')}"`;
}

function findHsnMasterRow(code) {
    if (!code) return null;
    const clean = String(code).trim().toUpperCase();
    return Array.isArray(createdHsnList)
        ? (createdHsnList.find(item => item.hsn_code.toUpperCase() === clean && (item.code_type || 'HSN') === 'HSN') || null)
        : null;
}

function setHsnInputMetadata(input, code) {
    const row = findHsnMasterRow(code);
    input.dataset.hsnGstRate = (row?.gst_rate !== null && row?.gst_rate !== undefined && row?.gst_rate !== '') ? row.gst_rate : '';
    input.dataset.hsnRateNote = row?.rate_note || '';
}

function resolveHsnInputMetadata(input) {
    if (input) {
        setHsnInputMetadata(input, input.value);
    }
}

function setHsnInputValue(input, row) {
    input.value = row.hsn_code || '';
    input.dataset.hsnGstRate = row.gst_rate ?? '';
    input.dataset.hsnRateNote = row.rate_note || '';
}

let unitOptions = [
    'Pieces',
    'Kilograms',
    'Metres',
    'Litres',
    'Boxes',
    'Packs',
    'Pairs',
    'Sets',
    'Bundles',
    'Other'
];
let currentUnitTarget = 0;

function getUnitOptionsHtml(selectedVal = 'Pieces') {
    let html = '';
    unitOptions.forEach(function(u) {
        const isSel = (u.toLowerCase() === (selectedVal || '').toLowerCase()) ? 'selected' : '';
        html += `<option value="${escapeHtml(u)}" ${isSel}>${escapeHtml(u)}</option>`;
    });
    html += `<option value="__add_new__" class="fw-bold text-primary">+ Add New Unit...</option>`;
    return html;
}

function promptAddUnit(targetId) {
    currentUnitTarget = targetId;
    document.getElementById('newUnitName').value = '';
    const modal = new bootstrap.Modal(document.getElementById('modalQuickUnit'));
    modal.show();
}

function onUnitChange(select, targetId) {
    if (select.value === '__add_new__') {
        promptAddUnit(targetId);
        select.value = unitOptions[0];
    } else {
        syncOtherTable();
    }
}

function saveAndSelectUnit() {
    const raw = document.getElementById('newUnitName').value.trim();
    if (!raw) {
        alert('Please enter a unit name.');
        return;
    }
    const unitName = raw.charAt(0).toUpperCase() + raw.slice(1);
    if (!unitOptions.includes(unitName)) {
        unitOptions.push(unitName);
    }

    document.querySelectorAll('.other-input-unit').forEach(function(select) {
        const currentVal = select.value;
        select.innerHTML = getUnitOptionsHtml(currentVal);
    });

    if (currentUnitTarget) {
        const targetSelect = document.querySelector(`#otherCard_${currentUnitTarget} .other-input-unit`);
        if (targetSelect) {
            targetSelect.value = unitName;
        }
        syncOtherTable();
    }

    const modalEl = document.getElementById('modalQuickUnit');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

function promptAddHsn(targetId, targetType = 'rolls') {
    currentHsnTarget = { type: targetType, id: targetId };
    document.getElementById('newHsnCode').value = '';
    document.getElementById('newHsnDesc').value = '';
    document.getElementById('newHsnGstRate').value = '';
    document.getElementById('newHsnRateNote').value = '';
    const modal = new bootstrap.Modal(document.getElementById('modalQuickHsn'));
    modal.show();
}

function saveAndSelectHsn() {
    const code = document.getElementById('newHsnCode').value.trim().toUpperCase();
    const desc = document.getElementById('newHsnDesc').value.trim();
    const rateInput = document.getElementById('newHsnGstRate').value.trim();
    const rate = rateInput !== '' ? parseFloat(rateInput) : null;
    const rateNote = document.getElementById('newHsnRateNote').value.trim();

    if (!code) {
        alert('Please enter an HSN code.');
        return;
    }
    if (rateInput !== '' && (!Number.isFinite(rate) || rate < 0 || rate > 100)) {
        alert('Enter a valid GST rate between 0 and 100, or leave it blank.');
        return;
    }

    const existing = createdHsnList.find(item => item.hsn_code === code && item.code_type === 'HSN');
    if (existing) {
        applyCreatedHsn(existing);
        return;
    }

    const formData = new FormData();
    formData.append('hsn_code', code);
    formData.append('code_type', 'HSN');
    formData.append('description', desc);
    formData.append('category', 'Garment and textile goods');
    formData.append('gst_rate', rate === null ? '' : rate);
    formData.append('rate_note', rateNote);

    fetch('<?= \App\Core\Controller::url('/m/purchase_billing/save_hsn') ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => {
        if (!r.ok) throw new Error('Unable to save HSN code.');
        return r.json();
    })
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Unable to save HSN code.');
        applyCreatedHsn(data);
    })
    .catch(err => {
        alert(err.message || 'Unable to save HSN code. Please try again.');
    });
}

function applyCreatedHsn(hsn) {
    const { hsn_code: code, gst_rate: rate, rate_note: rateNote = '', code_type: type = 'HSN' } = hsn;
    const exists = createdHsnList.some(item => item.hsn_code === code && item.code_type === type);
    if (!exists) {
        createdHsnList.push(hsn);
    } else {
        createdHsnList = createdHsnList.map(item => item.hsn_code === code && item.code_type === type ? hsn : item);
    }

    const selectedHsn = { hsn_code: code, gst_rate: rate, rate_note: rateNote };

    if (currentHsnTarget.type === 'rolls') {
        const activeInput = document.querySelector(`#rollCard_${currentHsnTarget.id} .roll-input-hsn`);
        if (activeInput) setHsnInputValue(activeInput, selectedHsn);
        onRollHsnChange(currentHsnTarget.id);
        syncRollsTable();
    } else if (currentHsnTarget.type === 'other') {
        const activeInput = document.querySelector(`#otherCard_${currentHsnTarget.id} .other-input-hsn`);
        if (activeInput) setHsnInputValue(activeInput, selectedHsn);
        onOtherHsnChange(currentHsnTarget.id);
        syncOtherTable();
    } else if (currentHsnTarget.type === 'finished') {
        const activeInput = document.querySelector(`#finishedCard_${currentHsnTarget.id} .fg-input-hsn`);
        if (activeInput) setHsnInputValue(activeInput, selectedHsn);
        onFinishedHsnChange(currentHsnTarget.id);
        syncFinishedTable();
    }

    const modalEl = document.getElementById('modalQuickHsn');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

function previewRollPhoto(input, rollId) {
    const card = document.getElementById(`rollCard_${rollId}`);
    if (!card) return;

    const file = input.files && input.files[0];
    const previewContainer = document.getElementById(`photoPreview_${rollId}`);

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const dataUrl = e.target.result;
            card.dataset.photoUrl = dataUrl;
            card.dataset.photoName = file.name;

            if (previewContainer) {
                previewContainer.innerHTML = `
                    <div class="d-inline-flex align-items-center gap-2 p-1 px-2 mt-1 bg-white border rounded shadow-sm">
                        <a href="${dataUrl}" target="_blank" title="View Full Image">
                            <img src="${dataUrl}" class="rounded border" style="width: 32px; height: 32px; object-fit: cover;" alt="Preview">
                        </a>
                        <span class="small fw-semibold text-truncate text-dark" style="max-width: 180px;">${file.name}</span>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="clearRollPhoto(${rollId})" title="Remove photo">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                `;
            }
            syncRollsTable();
        };
        reader.readAsDataURL(file);
    } else {
        delete card.dataset.photoUrl;
        delete card.dataset.photoName;
        if (previewContainer) previewContainer.innerHTML = '';
        syncRollsTable();
    }
}

function clearRollPhoto(rollId) {
    const card = document.getElementById(`rollCard_${rollId}`);
    if (!card) return;
    const fileInput = card.querySelector('.roll-input-photo');
    if (fileInput) fileInput.value = '';
    delete card.dataset.photoUrl;
    delete card.dataset.photoName;
    const previewContainer = document.getElementById(`photoPreview_${rollId}`);
    if (previewContainer) previewContainer.innerHTML = '';
    syncRollsTable();
}

function switchSectionAction(section) {
    // Header quick action button removed per user request
}

function onRollGstTypeChange(rollId) {
    const card = document.getElementById('rollCard_' + rollId);
    if (!card) return;

    const gstSelect = card.querySelector('.roll-input-gst');
    const gstType = gstSelect?.value || 'Exempted';
    const dynamicContainer = document.getElementById('gstDynamic_' + rollId);
    const sgstCol = document.getElementById('sgstCol_' + rollId);
    const cgstCol = document.getElementById('cgstCol_' + rollId);
    const igstCol = document.getElementById('igstCol_' + rollId);

    const hsnInput = card.querySelector('.roll-input-hsn');
    const hsnGst = parseFloat(hsnInput?.dataset?.hsnGstRate) || 0;

    if (gstType === 'Exempted') {
        // When Exempted: dynamic fields do NOT come
        if (dynamicContainer) dynamicContainer.classList.add('d-none');
    } else {
        // Show fields based on Image 2
        if (dynamicContainer) dynamicContainer.classList.remove('d-none');

        const taxType = card.querySelector('.roll-input-tax-type')?.value || 'Percentage';
        const isPct = (taxType === 'Percentage');

        if (gstType === 'Both (CGST and SGST)') {
            if (sgstCol) sgstCol.classList.remove('d-none');
            if (cgstCol) cgstCol.classList.remove('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const sgstInput = card.querySelector('.roll-input-sgst');
            const cgstInput = card.querySelector('.roll-input-cgst');
            if (isPct && hsnGst > 0 && (parseFloat(sgstInput.value) === 0 || !sgstInput.value)) {
                sgstInput.value = (hsnGst / 2).toFixed(2);
                cgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'CGST') {
            if (sgstCol) sgstCol.classList.add('d-none');
            if (cgstCol) cgstCol.classList.remove('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const cgstInput = card.querySelector('.roll-input-cgst');
            if (isPct && hsnGst > 0 && (parseFloat(cgstInput.value) === 0 || !cgstInput.value)) {
                cgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'SGST') {
            if (sgstCol) sgstCol.classList.remove('d-none');
            if (cgstCol) cgstCol.classList.add('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const sgstInput = card.querySelector('.roll-input-sgst');
            if (isPct && hsnGst > 0 && (parseFloat(sgstInput.value) === 0 || !sgstInput.value)) {
                sgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'IGST') {
            if (sgstCol) sgstCol.classList.add('d-none');
            if (cgstCol) cgstCol.classList.add('d-none');
            if (igstCol) igstCol.classList.remove('d-none');

            const igstInput = card.querySelector('.roll-input-igst');
            if (isPct && hsnGst > 0 && (parseFloat(igstInput.value) === 0 || !igstInput.value)) {
                igstInput.value = hsnGst.toFixed(2);
            }
        }
    }

    syncRollsTable();
}

function onRollTaxInputTypeChange(rollId) {
    const card = document.getElementById('rollCard_' + rollId);
    if (!card) return;
    const taxType = card.querySelector('.roll-input-tax-type')?.value || 'Percentage';
    const isPct = (taxType === 'Percentage');

    const sgstLabel = card.querySelector('.roll-sgst-label');
    const cgstLabel = card.querySelector('.roll-cgst-label');
    const igstLabel = card.querySelector('.roll-igst-label');

    if (sgstLabel) sgstLabel.textContent = isPct ? 'SGST (%)' : 'SGST (₹)';
    if (cgstLabel) cgstLabel.textContent = isPct ? 'CGST (%)' : 'CGST (₹)';
    if (igstLabel) igstLabel.textContent = isPct ? 'IGST (%)' : 'IGST (₹)';

    syncRollsTable();
}

function onRollHsnChange(rollId) {
    const card = document.getElementById('rollCard_' + rollId);
    if (!card) return;
    const hsnInput = card.querySelector('.roll-input-hsn');
    resolveHsnInputMetadata(hsnInput);
    updateHsnRateNote(hsnInput);
    applyHsnRateToGstInputs(card, 'roll');
    onRollGstTypeChange(rollId);
}

function updateHsnRateNote(input) {
    if (!input) return;
    const note = input.parentElement.querySelector('.hsn-rate-note');
    if (note) {
        note.textContent = input.dataset.hsnRateNote || (input.value ? 'Verify the applicable GST rate for this classification.' : '');
    }
}

function applyHsnRateToGstInputs(card, prefix) {
    const taxType = card.querySelector(`.${prefix}-input-tax-type`)?.value || 'Percentage';
    if (taxType !== 'Percentage') return;

    const hsnInput = card.querySelector(`.${prefix}-input-hsn`);
    const rateText = hsnInput?.dataset?.hsnGstRate ?? '';
    const gstRate = rateText === '' ? null : Number(rateText);
    const sgstInput = card.querySelector(`.${prefix}-input-sgst`);
    const cgstInput = card.querySelector(`.${prefix}-input-cgst`);
    const igstInput = card.querySelector(`.${prefix}-input-igst`);

    [sgstInput, cgstInput, igstInput].forEach(input => {
        if (input) input.value = '';
    });

    if (gstRate === null || !Number.isFinite(gstRate) || gstRate < 0 || gstRate > 100) return;

    const gstSelect = card.querySelector(`.${prefix}-input-gst`);
    let gstType = gstSelect?.value || 'Exempted';

    // Auto-switch to 'Both (CGST and SGST)' if currently Exempted and HSN has rate > 0
    if (gstType === 'Exempted' && gstRate > 0 && gstSelect) {
        gstSelect.value = 'Both (CGST and SGST)';
        gstType = 'Both (CGST and SGST)';
    }

    const halfRate = (gstRate / 2).toFixed(2);
    if (gstType === 'Both (CGST and SGST)') {
        if (sgstInput) sgstInput.value = halfRate;
        if (cgstInput) cgstInput.value = halfRate;
    } else if (gstType === 'CGST') {
        if (cgstInput) cgstInput.value = halfRate;
    } else if (gstType === 'SGST') {
        if (sgstInput) sgstInput.value = halfRate;
    } else if (gstType === 'IGST' && igstInput) {
        igstInput.value = gstRate.toFixed(2);
    }
}

function onRollHsnInput(rollId) {
    const card = document.getElementById('rollCard_' + rollId);
    if (!card) return;
    const hsnInput = card.querySelector('.roll-input-hsn');
    if (!hsnInput) return;
    resolveHsnInputMetadata(hsnInput);
    updateHsnRateNote(hsnInput);
    const rateText = hsnInput.dataset.hsnGstRate;
    if (rateText !== '' && rateText !== undefined && rateText !== null) {
        applyHsnRateToGstInputs(card, 'roll');
        onRollGstTypeChange(rollId);
    }
}

function onOtherGstTypeChange(otherId) {
    const card = document.getElementById('otherCard_' + otherId);
    if (!card) return;

    const gstSelect = card.querySelector('.other-input-gst');
    const gstType = gstSelect?.value || 'Exempted';
    const dynamicContainer = document.getElementById('otherGstDynamic_' + otherId);
    const sgstCol = document.getElementById('otherSgstCol_' + otherId);
    const cgstCol = document.getElementById('otherCgstCol_' + otherId);
    const igstCol = document.getElementById('otherIgstCol_' + otherId);

    const hsnInput = card.querySelector('.other-input-hsn');
    const hsnGst = parseFloat(hsnInput?.dataset?.hsnGstRate) || 0;

    if (gstType === 'Exempted') {
        if (dynamicContainer) dynamicContainer.classList.add('d-none');
    } else {
        if (dynamicContainer) dynamicContainer.classList.remove('d-none');

        const taxType = card.querySelector('.other-input-tax-type')?.value || 'Percentage';
        const isPct = (taxType === 'Percentage');

        if (gstType === 'Both (CGST and SGST)') {
            if (sgstCol) sgstCol.classList.remove('d-none');
            if (cgstCol) cgstCol.classList.remove('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const sgstInput = card.querySelector('.other-input-sgst');
            const cgstInput = card.querySelector('.other-input-cgst');
            if (isPct && hsnGst > 0 && (parseFloat(sgstInput.value) === 0 || !sgstInput.value)) {
                sgstInput.value = (hsnGst / 2).toFixed(2);
                cgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'CGST') {
            if (sgstCol) sgstCol.classList.add('d-none');
            if (cgstCol) cgstCol.classList.remove('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const cgstInput = card.querySelector('.other-input-cgst');
            if (isPct && hsnGst > 0 && (parseFloat(cgstInput.value) === 0 || !cgstInput.value)) {
                cgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'SGST') {
            if (sgstCol) sgstCol.classList.remove('d-none');
            if (cgstCol) cgstCol.classList.add('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const sgstInput = card.querySelector('.other-input-sgst');
            if (isPct && hsnGst > 0 && (parseFloat(sgstInput.value) === 0 || !sgstInput.value)) {
                sgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'IGST') {
            if (sgstCol) sgstCol.classList.add('d-none');
            if (cgstCol) cgstCol.classList.add('d-none');
            if (igstCol) igstCol.classList.remove('d-none');

            const igstInput = card.querySelector('.other-input-igst');
            if (isPct && hsnGst > 0 && (parseFloat(igstInput.value) === 0 || !igstInput.value)) {
                igstInput.value = hsnGst.toFixed(2);
            }
        }
    }
    syncOtherTable();
}

function onOtherTaxInputTypeChange(otherId) {
    const card = document.getElementById('otherCard_' + otherId);
    if (!card) return;
    const taxType = card.querySelector('.other-input-tax-type')?.value || 'Percentage';
    const isPct = (taxType === 'Percentage');

    const sgstLabel = card.querySelector('.other-sgst-label');
    const cgstLabel = card.querySelector('.other-cgst-label');
    const igstLabel = card.querySelector('.other-igst-label');

    if (sgstLabel) sgstLabel.textContent = isPct ? 'SGST (%)' : 'SGST (₹)';
    if (cgstLabel) cgstLabel.textContent = isPct ? 'CGST (%)' : 'CGST (₹)';
    if (igstLabel) igstLabel.textContent = isPct ? 'IGST (%)' : 'IGST (₹)';

    syncOtherTable();
}

function onOtherHsnChange(otherId) {
    const card = document.getElementById('otherCard_' + otherId);
    if (!card) return;
    const hsnInput = card.querySelector('.other-input-hsn');
    resolveHsnInputMetadata(hsnInput);
    updateHsnRateNote(hsnInput);
    applyHsnRateToGstInputs(card, 'other');
    onOtherGstTypeChange(otherId);
}

function onOtherHsnInput(otherId) {
    const card = document.getElementById('otherCard_' + otherId);
    if (!card) return;
    const hsnInput = card.querySelector('.other-input-hsn');
    if (!hsnInput) return;
    resolveHsnInputMetadata(hsnInput);
    updateHsnRateNote(hsnInput);
    const rateText = hsnInput.dataset.hsnGstRate;
    if (rateText !== '' && rateText !== undefined && rateText !== null) {
        applyHsnRateToGstInputs(card, 'other');
        onOtherGstTypeChange(otherId);
    }
}

function onFinishedGstTypeChange(fgId) {
    const card = document.getElementById('finishedCard_' + fgId);
    if (!card) return;

    const gstSelect = card.querySelector('.fg-input-gst');
    const gstType = gstSelect?.value || 'Exempted';
    const dynamicContainer = document.getElementById('fgGstDynamic_' + fgId);
    const sgstCol = document.getElementById('fgSgstCol_' + fgId);
    const cgstCol = document.getElementById('fgCgstCol_' + fgId);
    const igstCol = document.getElementById('fgIgstCol_' + fgId);

    const hsnInput = card.querySelector('.fg-input-hsn');
    const hsnGst = parseFloat(hsnInput?.dataset?.hsnGstRate) || 0;

    if (gstType === 'Exempted') {
        if (dynamicContainer) dynamicContainer.classList.add('d-none');
    } else {
        if (dynamicContainer) dynamicContainer.classList.remove('d-none');

        const taxType = card.querySelector('.fg-input-tax-type')?.value || 'Percentage';
        const isPct = (taxType === 'Percentage');

        if (gstType === 'Both (CGST and SGST)') {
            if (sgstCol) sgstCol.classList.remove('d-none');
            if (cgstCol) cgstCol.classList.remove('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const sgstInput = card.querySelector('.fg-input-sgst');
            const cgstInput = card.querySelector('.fg-input-cgst');
            if (isPct && hsnGst > 0 && (parseFloat(sgstInput.value) === 0 || !sgstInput.value)) {
                sgstInput.value = (hsnGst / 2).toFixed(2);
                cgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'CGST') {
            if (sgstCol) sgstCol.classList.add('d-none');
            if (cgstCol) cgstCol.classList.remove('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const cgstInput = card.querySelector('.fg-input-cgst');
            if (isPct && hsnGst > 0 && (parseFloat(cgstInput.value) === 0 || !cgstInput.value)) {
                cgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'SGST') {
            if (sgstCol) sgstCol.classList.remove('d-none');
            if (cgstCol) cgstCol.classList.add('d-none');
            if (igstCol) igstCol.classList.add('d-none');

            const sgstInput = card.querySelector('.fg-input-sgst');
            if (isPct && hsnGst > 0 && (parseFloat(sgstInput.value) === 0 || !sgstInput.value)) {
                sgstInput.value = (hsnGst / 2).toFixed(2);
            }
        } else if (gstType === 'IGST') {
            if (sgstCol) sgstCol.classList.add('d-none');
            if (cgstCol) cgstCol.classList.add('d-none');
            if (igstCol) igstCol.classList.remove('d-none');

            const igstInput = card.querySelector('.fg-input-igst');
            if (isPct && hsnGst > 0 && (parseFloat(igstInput.value) === 0 || !igstInput.value)) {
                igstInput.value = hsnGst.toFixed(2);
            }
        }
    }
    syncFinishedTable();
}

function onFinishedTaxInputTypeChange(fgId) {
    const card = document.getElementById('finishedCard_' + fgId);
    if (!card) return;
    const taxType = card.querySelector('.fg-input-tax-type')?.value || 'Percentage';
    const isPct = (taxType === 'Percentage');

    const sgstLabel = card.querySelector('.fg-sgst-label');
    const cgstLabel = card.querySelector('.fg-cgst-label');
    const igstLabel = card.querySelector('.fg-igst-label');

    if (sgstLabel) sgstLabel.textContent = isPct ? 'SGST (%)' : 'SGST (₹)';
    if (cgstLabel) cgstLabel.textContent = isPct ? 'CGST (%)' : 'CGST (₹)';
    if (igstLabel) igstLabel.textContent = isPct ? 'IGST (%)' : 'IGST (₹)';

    syncFinishedTable();
}

function onFinishedHsnChange(fgId) {
    const card = document.getElementById('finishedCard_' + fgId);
    if (!card) return;
    const hsnInput = card.querySelector('.fg-input-hsn');
    resolveHsnInputMetadata(hsnInput);
    updateHsnRateNote(hsnInput);
    applyHsnRateToGstInputs(card, 'fg');
    onFinishedGstTypeChange(fgId);
}

function onFinishedHsnInput(fgId) {
    const card = document.getElementById('finishedCard_' + fgId);
    if (!card) return;
    const hsnInput = card.querySelector('.fg-input-hsn');
    if (!hsnInput) return;
    resolveHsnInputMetadata(hsnInput);
    updateHsnRateNote(hsnInput);
    const rateText = hsnInput.dataset.hsnGstRate;
    if (rateText !== '' && rateText !== undefined && rateText !== null) {
        applyHsnRateToGstInputs(card, 'fg');
        onFinishedGstTypeChange(fgId);
    }
}

// ==========================================
// 1. ROLLS LOGIC (IMAGE 1, 2 & 3)
// ==========================================
let targetRollRowIndex = 1;

function getSavedRollSpecsOptionsHtml(selectedVal = '') {
    let html = '<option value="">-- Choose from Saved Color & Design Registry --</option>';
    if (Array.isArray(savedRollSpecsRegistry) && savedRollSpecsRegistry.length > 0) {
        html += '<optgroup label="Saved Specifications">';
        savedRollSpecsRegistry.forEach(spec => {
            const qtyVal = parseFloat(spec.qty) || 0;
            const wtVal = parseFloat(spec.net_weight) || 0;
            const priceVal = parseFloat(spec.last_unit_price) || 0;
            const val = `${spec.design_name}|${spec.color}`;
            const isSel = (selectedVal && selectedVal.toUpperCase() === val.toUpperCase()) ? 'selected' : '';
            html += `<option value="${escapeHtml(val)}" 
                             data-color="${escapeHtml(spec.color)}" 
                             data-pattern="${escapeHtml(spec.design_name)}" 
                             data-qty="${qtyVal}" 
                             data-weight="${wtVal}" 
                             data-price="${priceVal}" 
                             data-hsn="${escapeHtml(spec.hsn_code || '')}" 
                             data-supplier="${escapeHtml(spec.last_supplier_name || '')}" 
                             data-bill="${escapeHtml(spec.last_bill_no || '')}" 
                             data-bills="${parseInt(spec.bills_count || 1)}" 
                             ${isSel}>
                        ${escapeHtml(spec.design_name)} &bull; ${escapeHtml(spec.color)} (Available: ${qtyVal} Rolls)
                     </option>`;
        });
        html += '</optgroup>';
    }
    return html;
}

function onRollColorOrPatternChange(idx) {
    const card = document.getElementById('rollCard_' + idx);
    if (!card) return;
    const colorInp = card.querySelector('.roll-input-color');
    const patInp = card.querySelector('.roll-input-pattern');
    const qtyInp = card.querySelector('.roll-input-rq');
    const noticeDiv = document.getElementById('rollStockNotice_' + idx);
    if (!colorInp || !patInp || !noticeDiv) return;

    const colorVal = colorInp.value.trim().toUpperCase();
    const patVal = patInp.value.trim().toUpperCase();
    const qtyVal = parseFloat(qtyInp ? qtyInp.value : 0) || 1;

    if (colorVal && patVal && Array.isArray(savedRollSpecsRegistry) && savedRollSpecsRegistry.length > 0) {
        const match = savedRollSpecsRegistry.find(s => 
            s.color.toUpperCase() === colorVal && s.design_name.toUpperCase() === patVal
        );
        if (match) {
            const prevQty = parseFloat(match.qty) || 0;
            const prevWt = parseFloat(match.net_weight) || 0;
            const newTot = prevQty + qtyVal;
            noticeDiv.style.display = 'block';
            noticeDiv.className = 'roll-stock-notice mb-3 p-2.5 rounded bg-info-subtle text-info-emphasis border border-info-subtle';
            noticeDiv.innerHTML = `
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <i class="bi bi-clock-history text-info me-1"></i>
                        <strong>Registered Specification Found:</strong> Previous Count: <strong>${prevQty} Rolls</strong> (${prevWt.toFixed(2)} kg).
                    </div>
                    <div>
                        <span class="badge bg-success text-white px-2 py-1">New Accumulated Stock: ${newTot} Rolls</span>
                    </div>
                </div>
                <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                    Entering this bill will add <strong>${qtyVal} roll(s)</strong> to the previous count. Duplicate register entries will be removed.
                </div>
            `;
            return;
        }
    }
    noticeDiv.style.display = 'none';
    noticeDiv.innerHTML = '';
}

function applySavedRollSpec(selectEl, idx) {
    if (!selectEl) return;
    const val = selectEl.value;

    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) return;
    const card = document.getElementById('rollCard_' + idx);
    if (!card) return;

    const color = opt.getAttribute('data-color') || '';
    const pattern = opt.getAttribute('data-pattern') || '';
    const price = parseFloat(opt.getAttribute('data-price') || 0);
    const hsn = opt.getAttribute('data-hsn') || '';

    const colorInp = card.querySelector('.roll-input-color');
    const patInp = card.querySelector('.roll-input-pattern');
    const priceInp = card.querySelector('.roll-input-price');
    const hsnSel = card.querySelector('.roll-input-hsn');

    if (colorInp) colorInp.value = color;
    if (patInp) patInp.value = pattern;
    if (priceInp && price > 0 && (!priceInp.value || parseFloat(priceInp.value) === 0)) {
        priceInp.value = price.toFixed(2);
    }
    if (hsnSel && hsn) {
        hsnSel.value = hsn;
        setHsnInputMetadata(hsnSel, hsn);
        onRollHsnChange(idx);
    }

    onRollColorOrPatternChange(idx);
    syncRollsTable();
}

function viewCurrentRollSpec(idx) {
    targetRollRowIndex = idx || 1;
    const card = document.getElementById('rollCard_' + idx);
    const selectEl = document.getElementById('rollQuickSelect_' + idx);

    let selectedPattern = '';
    let selectedColor = '';

    if (selectEl && selectEl.value) {
        const parts = selectEl.value.split('|');
        selectedPattern = parts[0] || '';
        selectedColor = parts[1] || '';
    } else if (card) {
        const colorInp = card.querySelector('.roll-input-color');
        const patInp = card.querySelector('.roll-input-pattern');
        selectedColor = colorInp ? colorInp.value.trim().toUpperCase() : '';
        selectedPattern = patInp ? patInp.value.trim().toUpperCase() : '';
    }

    let foundSpec = null;
    if (selectedPattern && selectedColor && Array.isArray(savedRollSpecsRegistry)) {
        foundSpec = savedRollSpecsRegistry.find(s => 
            s.design_name.toUpperCase() === selectedPattern.toUpperCase() && 
            s.color.toUpperCase() === selectedColor.toUpperCase()
        );
    }

    openViewRollSpecsModal(idx, foundSpec);
}

function openCreateDesignModal(idx) {
    targetRollRowIndex = idx || 1;
    document.getElementById('newDesignTargetRow').value = targetRollRowIndex;

    document.getElementById('newDesignPattern').value = '';
    document.getElementById('newDesignColor').value = '';
    document.getElementById('newDesignPrice').value = '';
    document.getElementById('newDesignHsn').value = '';

    const card = document.getElementById('rollCard_' + targetRollRowIndex);
    if (card) {
        const colorInp = card.querySelector('.roll-input-color');
        const patInp = card.querySelector('.roll-input-pattern');
        const priceInp = card.querySelector('.roll-input-price');
        const hsnSel = card.querySelector('.roll-input-hsn');
        if (colorInp && colorInp.value) document.getElementById('newDesignColor').value = colorInp.value.trim().toUpperCase();
        if (patInp && patInp.value) document.getElementById('newDesignPattern').value = patInp.value.trim().toUpperCase();
        if (priceInp && priceInp.value) document.getElementById('newDesignPrice').value = priceInp.value;
        if (hsnSel && hsnSel.value) document.getElementById('newDesignHsn').value = hsnSel.value;
    }

    const viewModalEl = document.getElementById('modalViewRollSpecs');
    const viewModal = bootstrap.Modal.getInstance(viewModalEl);
    if (viewModal) viewModal.hide();

    const modal = new bootstrap.Modal(document.getElementById('modalCreateRollDesign'));
    modal.show();
}

function saveAndApplyRollDesign() {
    const rowIdx = parseInt(document.getElementById('newDesignTargetRow').value) || 1;
    const pattern = document.getElementById('newDesignPattern').value.trim().toUpperCase();
    const color = document.getElementById('newDesignColor').value.trim().toUpperCase();
    const price = parseFloat(document.getElementById('newDesignPrice').value) || 0.0;
    const hsn = document.getElementById('newDesignHsn').value.trim().toUpperCase();
    const weight = 0.0;
    const supplier = '';

    if (!pattern || !color) {
        alert('Please provide both Design Name / Pattern and Color / Shade.');
        return;
    }

    const btn = document.getElementById('btnSubmitRollDesign');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    const formData = new FormData();
    formData.append('design_name', pattern);
    formData.append('color', color);
    formData.append('unit_price', price);
    formData.append('hsn_code', hsn);
    formData.append('net_weight', weight);
    formData.append('supplier_name', supplier);
    <?php if ($selectedCid): ?>
    formData.append('concern_id', '<?= (int)$selectedCid ?>');
    <?php endif; ?>

    fetch('<?= \App\Core\Controller::url('/m/purchase_billing/save_roll_spec') ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save & Apply to Roll';

        if (res.success) {
            const spec = res.spec || {
                design_name: pattern,
                color: color,
                last_unit_price: price,
                hsn_code: hsn,
                net_weight: weight,
                qty: 0,
                unit: 'Rolls',
                bills_count: 0,
                last_bill_no: '',
                last_supplier_name: supplier
            };

            const existingIdx = savedRollSpecsRegistry.findIndex(s => 
                s.design_name.toUpperCase() === pattern && s.color.toUpperCase() === color
            );
            if (existingIdx >= 0) {
                savedRollSpecsRegistry[existingIdx] = spec;
            } else {
                savedRollSpecsRegistry.push(spec);
                savedRollSpecsRegistry.sort((a,b) => a.design_name.localeCompare(b.design_name));
            }

            updateAllRollQuickSelects(`${pattern}|${color}`, rowIdx);

            const card = document.getElementById('rollCard_' + rowIdx);
            if (card) {
                const colorInp = card.querySelector('.roll-input-color');
                const patInp = card.querySelector('.roll-input-pattern');
                const priceInp = card.querySelector('.roll-input-price');
                const hsnSel = card.querySelector('.roll-input-hsn');
                const wtInp = card.querySelector('.roll-input-weight');

                if (colorInp) colorInp.value = color;
                if (patInp) patInp.value = pattern;
                if (priceInp && price > 0) priceInp.value = price.toFixed(2);
                if (wtInp && weight > 0 && (!wtInp.value || parseFloat(wtInp.value) === 0)) wtInp.value = weight.toFixed(2);
                if (hsnSel && hsn) {
                    hsnSel.value = hsn;
                    setHsnInputMetadata(hsnSel, hsn);
                    onRollHsnChange(rowIdx);
                }

                onRollColorOrPatternChange(rowIdx);
                syncRollsTable();
            }

            updateRollDatalists(pattern, color);

            const modalEl = document.getElementById('modalCreateRollDesign');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            showToastNotice(`Design registered successfully: ${pattern} • ${color}`);
        } else {
            alert('Failed to register design: ' + (res.message || 'Unknown error'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save & Apply to Roll';
        console.error('Error saving roll spec:', err);
        alert('Server connection error. Please try again.');
    });
}

function updateAllRollQuickSelects(selectedVal = '', targetRowIdx = 1) {
    document.querySelectorAll('.roll-quick-select').forEach(sel => {
        const rowIdMatch = sel.id ? sel.id.match(/rollQuickSelect_(\d+)/) : null;
        const rowId = rowIdMatch ? parseInt(rowIdMatch[1]) : null;
        const currentVal = (rowId === targetRowIdx) ? selectedVal : sel.value;
        sel.innerHTML = getSavedRollSpecsOptionsHtml(currentVal);
    });
}

function updateRollDatalists(pattern, color) {
    const patList = document.getElementById('rollPatternList');
    if (patList) {
        let exists = false;
        for (let opt of patList.options) {
            if (opt.value.toUpperCase() === pattern.toUpperCase()) { exists = true; break; }
        }
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = pattern;
            patList.appendChild(opt);
        }
    }

    const colList = document.getElementById('rollColorList');
    if (colList) {
        let exists = false;
        for (let opt of colList.options) {
            if (opt.value.toUpperCase() === color.toUpperCase()) { exists = true; break; }
        }
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = color;
            colList.appendChild(opt);
        }
    }
}

function openViewRollSpecsModal(idx, foundSpec = null) {
    targetRollRowIndex = idx || 1;
    const detailCard = document.getElementById('viewSpecDetailCard');
    
    if (foundSpec) {
        detailCard.style.display = 'block';
        document.getElementById('vSpecPatternBadge').textContent = foundSpec.design_name || '-';
        document.getElementById('vSpecColorBadge').textContent = foundSpec.color || '-';
        document.getElementById('vSpecQty').textContent = (parseFloat(foundSpec.qty) || 0) + ' ' + (foundSpec.unit || 'Rolls');
        document.getElementById('vSpecWeight').textContent = (parseFloat(foundSpec.net_weight) || 0).toFixed(2) + ' kg';
        document.getElementById('vSpecPrice').textContent = '₹' + (parseFloat(foundSpec.last_unit_price) || 0).toFixed(2);
        document.getElementById('vSpecHsn').textContent = foundSpec.hsn_code || 'N/A';
        document.getElementById('vSpecBillsCount').textContent = foundSpec.bills_count || '0';
        document.getElementById('vSpecLastBill').textContent = foundSpec.last_bill_no || 'N/A';
        document.getElementById('vSpecSupplier').textContent = foundSpec.last_supplier_name || 'N/A';
    } else {
        detailCard.style.display = 'none';
    }

    const searchInput = document.getElementById('vSpecSearchInput');
    if (searchInput) searchInput.value = '';
    renderRegistrySpecsTable('');

    const modal = new bootstrap.Modal(document.getElementById('modalViewRollSpecs'));
    modal.show();
}

function renderRegistrySpecsTable(filter = '') {
    const tbody = document.getElementById('tbodyRegistrySpecs');
    const countSpan = document.getElementById('vSpecTotalCount');
    if (!tbody) return;

    if (!Array.isArray(savedRollSpecsRegistry) || savedRollSpecsRegistry.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">No saved color & design specifications registered yet. Click "+ Register New Design" to add one!</td></tr>`;
        if (countSpan) countSpan.textContent = '0';
        return;
    }

    const q = filter.trim().toUpperCase();
    const filtered = savedRollSpecsRegistry.filter(s => {
        if (!q) return true;
        return (s.design_name && s.design_name.toUpperCase().includes(q)) ||
               (s.color && s.color.toUpperCase().includes(q)) ||
               (s.hsn_code && s.hsn_code.toUpperCase().includes(q)) ||
               (s.last_supplier_name && s.last_supplier_name.toUpperCase().includes(q));
    });

    if (countSpan) countSpan.textContent = filtered.length;

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">No matching specifications found for "${escapeHtml(filter)}".</td></tr>`;
        return;
    }

    let rowsHtml = '';
    filtered.forEach(s => {
        const qty = parseFloat(s.qty) || 0;
        const wt = parseFloat(s.net_weight) || 0;
        const price = parseFloat(s.last_unit_price) || 0;
        const safeDesign = escapeHtml(s.design_name);
        const safeColor = escapeHtml(s.color);

        rowsHtml += `
            <tr>
                <td class="fw-bold text-dark font-monospace">${safeDesign}</td>
                <td><span class="badge bg-secondary-subtle text-secondary border font-monospace">${safeColor}</span></td>
                <td class="text-end fw-semibold text-primary">${qty} ${escapeHtml(s.unit || 'Rolls')}</td>
                <td class="text-end text-muted">${wt.toFixed(2)}</td>
                <td class="text-end fw-semibold text-success">₹${price.toFixed(2)}</td>
                <td class="text-muted">${escapeHtml(s.hsn_code || '-')}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-primary btn-sm py-0 px-2 fw-semibold" onclick="selectSpecFromRegistry('${safeDesign}', '${safeColor}')">
                        <i class="bi bi-arrow-down-left me-1"></i> Apply
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = rowsHtml;
}

function filterRegistrySpecsTable(val) {
    renderRegistrySpecsTable(val);
}

function selectSpecFromRegistry(pattern, color) {
    const val = `${pattern}|${color}`;
    const sel = document.getElementById('rollQuickSelect_' + targetRollRowIndex);
    if (sel) {
        sel.value = val;
        applySavedRollSpec(sel, targetRollRowIndex);
    } else {
        const spec = savedRollSpecsRegistry.find(s => 
            s.design_name.toUpperCase() === pattern.toUpperCase() && 
            s.color.toUpperCase() === color.toUpperCase()
        );
        if (spec) {
            const card = document.getElementById('rollCard_' + targetRollRowIndex);
            if (card) {
                const colorInp = card.querySelector('.roll-input-color');
                const patInp = card.querySelector('.roll-input-pattern');
                const priceInp = card.querySelector('.roll-input-price');
                const hsnSel = card.querySelector('.roll-input-hsn');

                if (colorInp) colorInp.value = spec.color;
                if (patInp) patInp.value = spec.design_name;
                if (priceInp && parseFloat(spec.last_unit_price) > 0) priceInp.value = parseFloat(spec.last_unit_price).toFixed(2);
                if (hsnSel && spec.hsn_code) {
                    hsnSel.value = spec.hsn_code;
                    setHsnInputMetadata(hsnSel, spec.hsn_code);
                    onRollHsnChange(targetRollRowIndex);
                }
                onRollColorOrPatternChange(targetRollRowIndex);
                syncRollsTable();
            }
        }
    }

    const modalEl = document.getElementById('modalViewRollSpecs');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    showToastNotice(`Applied: ${pattern} • ${color}`);
}

function showToastNotice(msg) {
    let toast = document.getElementById('rollSpecQuickToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'rollSpecQuickToast';
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        document.body.appendChild(toast);
    }
    toast.innerHTML = `
        <div class="toast align-items-center text-bg-dark border-0 show shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                    <span>${escapeHtml(msg)}</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    setTimeout(() => {
        if (toast) toast.innerHTML = '';
    }, 3500);
}

function addRollRow() {
    rollCounter++;
    document.getElementById('itemsValidationError')?.classList.add('d-none');
    const rollCode = generateRollCode(rollCounter);
    const container = document.getElementById('rollsContainer');
    document.getElementById('noRollsPlaceholder')?.classList.add('d-none');
    const div = document.createElement('div');
    div.className = 'roll-card card border mb-4 shadow-sm rounded-3';
    div.id = 'rollCard_' + rollCounter;

    const quickSelectHtml = `
        <div class="mb-3 p-2.5 rounded bg-light border d-flex flex-wrap align-items-center justify-content-between gap-2 roll-spec-selector-bar">
            <div class="d-flex align-items-center gap-1.5 small text-secondary">
                <i class="bi bi-bookmark-star text-primary"></i>
                <span class="fw-semibold">Roll Design / Pattern:</span>
            </div>
            <div class="flex-grow-1" style="max-width: 520px;">
                <div class="input-group input-group-sm">
                    <select class="form-select form-select-sm roll-quick-select" id="rollQuickSelect_${rollCounter}" onchange="applySavedRollSpec(this, ${rollCounter})">
                        ${getSavedRollSpecsOptionsHtml()}
                    </select>
                    <button type="button" class="btn btn-outline-info btn-sm btn-view-spec px-2" id="btnViewSpec_${rollCounter}" onclick="viewCurrentRollSpec(${rollCounter})" title="View Details of Selected Design">
                        <i class="bi bi-eye"></i> View
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-2 fw-semibold" onclick="openCreateDesignModal(${rollCounter})" title="Create New Design & Color">
                        <i class="bi bi-plus-lg"></i> New
                    </button>
                </div>
            </div>
        </div>
    `;

    div.innerHTML = `
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-layers text-primary"></i>
                <span class="fw-bold text-dark font-monospace">ROLL #${rollCounter}</span>
                <span class="badge bg-dark font-monospace px-2 py-1 roll-code-badge" id="rollCodeBadge_${rollCounter}">${rollCode}</span>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeRollRow(${rollCounter})">
                <i class="bi bi-trash3 me-1"></i> Delete
            </button>
        </div>
        <div class="card-body p-3">
            ${quickSelectHtml}
            <!-- Row 1: Roll No, Color, Pattern, Qty, Weight, Price -->
            <div class="row g-2 mb-2 align-items-end">
                <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Fabric Name <span class="text-danger">*</span></label>
                <input type="text" name="rolls[${rollCounter}][roll_code]" class="form-control form-control-sm font-monospace text-uppercase roll-input-code" placeholder="E.G. COTTON" value="" required oninput="updateRollHeaderCode(${rollCounter}); syncRollsTable()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Color / Shade <span class="text-danger">*</span></label>
                    <input type="text" name="rolls[${rollCounter}][color]" list="rollColorList" class="form-control form-control-sm text-uppercase roll-input-color" placeholder="E.G. BLACK" value="" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Design Name / Pattern <span class="text-danger">*</span></label>
                    <input type="text" name="rolls[${rollCounter}][pattern]" list="rollPatternList" class="form-control form-control-sm text-uppercase roll-input-pattern" placeholder="E.G. FLORAL 01" value="" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Qty <span class="text-danger">*</span></label>
                    <input type="number" min="1" step="1" name="rolls[${rollCounter}][rq]" class="form-control form-control-sm roll-input-rq" placeholder="1" value="1" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Overall Weight (kg) <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0.01" name="rolls[${rollCounter}][weight]" class="form-control form-control-sm roll-input-weight" placeholder="0.00" value="" required oninput="syncRollsTable()">
                        <span class="input-group-text small bg-light text-muted">kg</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Unit Price (₹) <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text small bg-light text-muted">₹</span>
                        <input type="number" step="0.01" min="0.01" name="rolls[${rollCounter}][price]" class="form-control form-control-sm roll-input-price" placeholder="0.00" value="" required oninput="syncRollsTable()">
                    </div>
                </div>
            </div>

            <!-- Live Roll Stock Accumulation Notice -->
            <div class="roll-stock-notice mb-3" id="rollStockNotice_${rollCounter}" style="display: none;"></div>

            <!-- Row 2: HSN Code, Tax Mode, GST & Dynamic Fields -->
            <div class="row g-2 mb-3 align-items-end">
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">HSN Code</label>
                        <a href="javascript:void(0)" class="text-decoration-none small text-primary fw-semibold" onclick="promptAddHsn(${rollCounter})">+ Add HSN</a>
                    </div>
                    <input type="text" name="rolls[${rollCounter}][hsn]" class="form-control form-control-sm font-monospace roll-input-hsn" data-hsn-autocomplete data-hsn-type="HSN" ${getHsnInputAttributes()} autocomplete="off" oninput="onRollHsnInput(${rollCounter})" onchange="onRollHsnChange(${rollCounter})">
                    <small class="hsn-rate-note text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Tax Mode</label>
                    <select name="rolls[${rollCounter}][tax_mode]" class="form-select form-select-sm roll-input-tax-mode" onchange="syncRollsTable()">
                        <option value="Exclude GST" selected>Exclude GST</option>
                        <option value="Include GST">Include GST</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">GST</label>
                    <select name="rolls[${rollCounter}][gst]" class="form-select form-select-sm roll-input-gst" onchange="onRollGstTypeChange(${rollCounter})">
                        <option value="Exempted" selected>Exempted</option>
                        <option value="Both (CGST and SGST)">Both (CGST and SGST)</option>
                        <option value="SGST">SGST</option>
                        <option value="CGST">CGST</option>
                        <option value="IGST">IGST</option>
                    </select>
                </div>
                <!-- Dynamic GST Fields Container -->
                <div class="col-md-5 d-none roll-gst-dynamic-container" id="gstDynamic_${rollCounter}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Tax Input Type</label>
                            <select name="rolls[${rollCounter}][tax_input_type]" class="form-select form-select-sm roll-input-tax-type" onchange="onRollTaxInputTypeChange(${rollCounter})">
                                <option value="Percentage" selected>Percentage (%)</option>
                                <option value="Amount">Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4 roll-sgst-col" id="sgstCol_${rollCounter}">
                            <label class="form-label small fw-semibold mb-1 roll-sgst-label">SGST (%)</label>
                            <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][sgst]" class="form-control form-control-sm roll-input-sgst" value="0" placeholder="0" oninput="syncRollsTable()">
                        </div>
                        <div class="col-md-4 roll-cgst-col" id="cgstCol_${rollCounter}">
                            <label class="form-label small fw-semibold mb-1 roll-cgst-label">CGST (%)</label>
                            <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][cgst]" class="form-control form-control-sm roll-input-cgst" value="0" placeholder="0" oninput="syncRollsTable()">
                        </div>
                        <div class="col-md-4 roll-igst-col d-none" id="igstCol_${rollCounter}">
                            <label class="form-label small fw-semibold mb-1 roll-igst-label">IGST (%)</label>
                            <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][igst]" class="form-control form-control-sm roll-input-igst" value="0" placeholder="0" oninput="syncRollsTable()">
                        </div>
                    </div>
                </div>
            </div>
            <!-- Row 3: Roll Sample Photo & Preview -->
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Roll Sample Photo (Optional — JPG, PNG, WEBP)</label>
                    <input type="file" name="rolls[${rollCounter}][photo]" class="form-control form-control-sm roll-input-photo" accept=".jpg,.jpeg,.png,.webp" onchange="previewRollPhoto(this, ${rollCounter})">
                    <div class="roll-photo-preview-inline mt-1" id="photoPreview_${rollCounter}"></div>
                </div>
            </div>
        </div>
    `;
    container.appendChild(div);
    syncRollsTable();
}

function removeRollRow(id) {
    const card = document.getElementById('rollCard_' + id);
    if (card) {
        card.remove();
        syncRollsTable();
    }
}

function calculateItemTax(card, prefix, qty, price) {
    const taxMode = card.querySelector(`.${prefix}-input-tax-mode`)?.value || 'Exclude GST';
    const gstType = card.querySelector(`.${prefix}-input-gst`)?.value || 'Exempted';
    const taxType = card.querySelector(`.${prefix}-input-tax-type`)?.value || 'Percentage';
    const isPct = (taxType === 'Percentage');

    const sgstVal = parseFloat(card.querySelector(`.${prefix}-input-sgst`)?.value) || 0;
    const cgstVal = parseFloat(card.querySelector(`.${prefix}-input-cgst`)?.value) || 0;
    const igstVal = parseFloat(card.querySelector(`.${prefix}-input-igst`)?.value) || 0;

    let base = 0;
    let tax = 0;
    let total = 0;
    let badge = '';
    let taxHtml = '';

    const gross = qty * price;

    if (gstType === 'Exempted') {
        base = gross;
        tax = 0;
        total = gross;
        badge = '<span class="badge bg-light text-dark border">EXEMPTED</span>';
        taxHtml = '<div class="fw-semibold">₹0.00</div>';
        return { base, tax, total, badge, taxHtml, taxMode };
    }

    if (taxMode === 'Include GST') {
        // Tax Inclusive: Gross is the final total value
        total = gross;
        if (!isPct) {
            if (gstType === 'Both (CGST and SGST)') {
                tax = sgstVal + cgstVal;
                badge = `<span class="badge bg-primary-subtle text-primary border">Both (CGST & SGST)</span> <span class="badge bg-info-subtle text-info border">INCL</span>`;
                taxHtml = `
                    <div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <small class="text-muted d-block" style="font-size: 0.72rem; white-space: nowrap;">CGST: ₹${cgstVal.toLocaleString('en-IN', { minimumFractionDigits: 2 })} + SGST: ₹${sgstVal.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</small>
                `;
            } else if (gstType === 'CGST') {
                tax = cgstVal;
                badge = `<span class="badge bg-primary-subtle text-primary border">CGST</span> <span class="badge bg-info-subtle text-info border">INCL</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            } else if (gstType === 'SGST') {
                tax = sgstVal;
                badge = `<span class="badge bg-primary-subtle text-primary border">SGST</span> <span class="badge bg-info-subtle text-info border">INCL</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            } else {
                tax = igstVal;
                badge = `<span class="badge bg-primary-subtle text-primary border">IGST</span> <span class="badge bg-info-subtle text-info border">INCL</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            }
            tax = Math.min(tax, gross);
            base = Math.max(0, gross - tax);
        } else {
            let rate = 0;
            if (gstType === 'Both (CGST and SGST)') {
                rate = sgstVal + cgstVal;
                base = rate > 0 ? (gross / (1 + (rate / 100))) : gross;
                tax = gross - base;
                const cgstAmt = rate > 0 ? (tax * (cgstVal / rate)) : 0;
                const sgstAmt = rate > 0 ? (tax * (sgstVal / rate)) : 0;
                badge = `<span class="badge bg-primary-subtle text-primary border">Both (${sgstVal}%+${cgstVal}%)</span> <span class="badge bg-info-subtle text-info border">INCL</span>`;
                taxHtml = `
                    <div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <small class="text-muted d-block" style="font-size: 0.72rem; white-space: nowrap;">CGST: ₹${cgstAmt.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} + SGST: ₹${sgstAmt.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</small>
                `;
            } else if (gstType === 'CGST') {
                rate = cgstVal;
                base = rate > 0 ? (gross / (1 + (rate / 100))) : gross;
                tax = gross - base;
                badge = `<span class="badge bg-primary-subtle text-primary border">CGST (${cgstVal}%)</span> <span class="badge bg-info-subtle text-info border">INCL</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            } else if (gstType === 'SGST') {
                rate = sgstVal;
                base = rate > 0 ? (gross / (1 + (rate / 100))) : gross;
                tax = gross - base;
                badge = `<span class="badge bg-primary-subtle text-primary border">SGST (${sgstVal}%)</span> <span class="badge bg-info-subtle text-info border">INCL</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            } else {
                rate = igstVal;
                base = rate > 0 ? (gross / (1 + (rate / 100))) : gross;
                tax = gross - base;
                badge = `<span class="badge bg-primary-subtle text-primary border">IGST (${igstVal}%)</span> <span class="badge bg-info-subtle text-info border">INCL</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            }
        }
    } else {
        // Exclude GST: Gross is the Base (Tax is added on top)
        base = gross;
        if (!isPct) {
            if (gstType === 'Both (CGST and SGST)') {
                tax = sgstVal + cgstVal;
                badge = `<span class="badge bg-primary-subtle text-primary border">Both (CGST & SGST)</span>`;
                taxHtml = `
                    <div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <small class="text-muted d-block" style="font-size: 0.72rem; white-space: nowrap;">CGST: ₹${cgstVal.toLocaleString('en-IN', { minimumFractionDigits: 2 })} + SGST: ₹${sgstVal.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</small>
                `;
            } else if (gstType === 'CGST') {
                tax = cgstVal;
                badge = `<span class="badge bg-primary-subtle text-primary border">CGST</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            } else if (gstType === 'SGST') {
                tax = sgstVal;
                badge = `<span class="badge bg-primary-subtle text-primary border">SGST</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            } else {
                tax = igstVal;
                badge = `<span class="badge bg-primary-subtle text-primary border">IGST</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            }
        } else {
            if (gstType === 'Both (CGST and SGST)') {
                const cgstAmt = base * (cgstVal / 100);
                const sgstAmt = base * (sgstVal / 100);
                tax = cgstAmt + sgstAmt;
                badge = `<span class="badge bg-primary-subtle text-primary border">Both (SGST: ${sgstVal}%, CGST: ${cgstVal}%)</span>`;
                taxHtml = `
                    <div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <small class="text-muted d-block" style="font-size: 0.72rem; white-space: nowrap;">CGST: ₹${cgstAmt.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} + SGST: ₹${sgstAmt.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</small>
                `;
            } else if (gstType === 'CGST') {
                tax = base * (cgstVal / 100);
                badge = `<span class="badge bg-primary-subtle text-primary border">CGST (${cgstVal}%)</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            } else if (gstType === 'SGST') {
                tax = base * (sgstVal / 100);
                badge = `<span class="badge bg-primary-subtle text-primary border">SGST (${sgstVal}%)</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            } else {
                tax = base * (igstVal / 100);
                badge = `<span class="badge bg-primary-subtle text-primary border">IGST (${igstVal}%)</span>`;
                taxHtml = `<div class="fw-bold text-dark">₹${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            }
        }
        total = base + tax;
    }

    return { base, tax, total, badge, taxHtml, taxMode };
}

function calculateRollTax(card, rq, price) {
    return calculateItemTax(card, 'roll', rq, price);
}

function syncRollsTable() {
    const tbody = document.getElementById('rollsInventoryTableBody');
    tbody.innerHTML = '';

    const cards = document.querySelectorAll('#rollsContainer .roll-card');
    let totalRolls = cards.length;
    let totalRq = 0;
    let totalWeight = 0;
    let totalTaxable = 0;
    let totalTax = 0;
    let colors = new Set();

    document.getElementById('rollBadgeCount').textContent = totalRolls;
    document.getElementById('rollCountHeader').textContent = totalRolls;
    document.getElementById('overviewRollsCount').textContent = totalRolls;

    if (totalRolls === 0) {
        document.getElementById('noRollsPlaceholder')?.classList.remove('d-none');
        tbody.innerHTML = '<tr><td colspan="13" class="text-center py-3 text-muted">Add fabric rolls to see inventory details.</td></tr>';
        document.getElementById('overviewRollsWeight').textContent = '0.00';
        document.getElementById('overviewRollsVal').textContent = '₹0.00';
        document.getElementById('metricRolls').textContent = '0 Rolls';
        document.getElementById('metricRq').textContent = '0';
        document.getElementById('metricColorsDesigns').textContent = '0 Colors';
        document.getElementById('metricWeight').textContent = '0.00 kg';
        document.getElementById('metricTotalInclTax').textContent = '₹0.00';
        recalcOverallSummary();
        return;
    }

    document.getElementById('noRollsPlaceholder')?.classList.add('d-none');

    cards.forEach(function(card, idx) {
        const id = card.id.replace('rollCard_', '');
        const rawCode = card.querySelector('.roll-input-code')?.value?.trim() || '';
        const code = rawCode || ('ROLL #' + (idx + 1));
        const rollBadgeCode = generateRollCode(parseInt(id) || (idx + 1), rawCode);
        const headerBadge = card.querySelector('.roll-code-badge');
        if (headerBadge) headerBadge.textContent = rollBadgeCode;

        const color = card.querySelector('.roll-input-color')?.value || '-';
        const pattern = card.querySelector('.roll-input-pattern')?.value || '-';
        const rq = parseInt(card.querySelector('.roll-input-rq')?.value) || 1;
        const weight = parseFloat(card.querySelector('.roll-input-weight')?.value) || 0;
        const price = parseFloat(card.querySelector('.roll-input-price')?.value) || 0;
        const hsnSelect = card.querySelector('.roll-input-hsn');
        const hsn = hsnSelect?.value || '-';

        // Qty == RQ!
        const qty = rq;
        const taxInfo = calculateRollTax(card, rq, price);
        const baseVal = taxInfo.base;
        const taxVal = taxInfo.tax;
        const totalVal = taxInfo.total;
        const gstBadge = taxInfo.badge;

        totalRq += rq;
        totalWeight += weight;
        totalTaxable += baseVal;
        totalTax += taxVal;
        if (color !== '-') colors.add(color.toUpperCase());

        const photoUrl = card.dataset.photoUrl;
        const photoHtml = photoUrl
            ? `<a href="${photoUrl}" target="_blank" title="View Full Roll Photo"><img src="${photoUrl}" class="rounded border shadow-sm" style="width: 36px; height: 36px; object-fit: cover; cursor: pointer;" alt="Roll Photo"></a>`
            : `<i class="bi bi-camera text-muted fs-6" title="No photo uploaded"></i>`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td>${photoHtml}</td>
            <td class="fw-bold font-monospace text-dark">${escapeHtml(code)} <small class="text-muted d-block">${escapeHtml(rollBadgeCode)}</small></td>
            <td class="font-monospace">${hsn}</td>
            <td>${color}</td>
            <td>${pattern}</td>
            <td class="fw-semibold text-primary">${weight.toFixed(2)} kg</td>
            <td>₹${price.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td class="fw-semibold">${qty}</td>
            <td class="fw-semibold">₹${baseVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td>${gstBadge}</td>
            <td>${taxInfo.taxHtml}</td>
            <td class="table-success fw-bold text-success">₹${totalVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        `;
        tbody.appendChild(tr);
    });

    // Update Counts & Badges
    document.getElementById('rollBadgeCount').textContent = totalRolls;
    document.getElementById('rollCountHeader').textContent = totalRolls;
    document.getElementById('overviewRollsCount').textContent = totalRolls;
    document.getElementById('overviewRollsWeight').textContent = totalWeight.toFixed(2);
    document.getElementById('overviewRollsVal').textContent = '₹' + (totalTaxable + totalTax).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Update Batch Metrics Summary (rollqty and total qty match)
    document.getElementById('metricRolls').textContent = totalRolls + (totalRolls === 1 ? ' Roll' : ' Rolls');
    document.getElementById('metricRq').textContent = totalRq;
    document.getElementById('metricColorsDesigns').textContent = `${colors.size} Colors`;
    document.getElementById('metricWeight').textContent = totalWeight.toFixed(2) + ' kg';
    document.getElementById('metricTotalInclTax').textContent = '₹' + (totalTaxable + totalTax).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    recalcOverallSummary();
}

// ==========================================
// 2. OTHER GARMENT SUPPLIES (IMAGE 2)
// ==========================================
function addOtherRow() {
    otherCounter++;
    document.getElementById('itemsValidationError')?.classList.add('d-none');
    const container = document.getElementById('garmentOtherContainer');
    document.getElementById('noSuppliesPlaceholder').classList.add('d-none');

    const div = document.createElement('div');
    div.className = 'other-card card border mb-3 shadow-sm rounded-3';
    div.id = 'otherCard_' + otherCounter;
    div.innerHTML = `
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-box-seam text-success"></i>
                <span class="fw-bold text-dark font-monospace">SUPPLY #${otherCounter}</span>
                <span class="badge bg-teal-subtle text-teal font-monospace px-2 py-1">TRIM-0${otherCounter}</span>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeOtherRow(${otherCounter})">
                <i class="bi bi-trash3 me-1"></i> Delete
            </button>
        </div>
        <div class="card-body p-3">
            <!-- Row 1: Code, Name, Quantity, Unit, Price -->
            <div class="row g-2 mb-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Other Product No/Code</label>
                    <input type="text" name="garment_other[${otherCounter}][product_no]" class="form-control form-control-sm font-monospace text-uppercase other-input-code" placeholder="e.g. TRIM-0${otherCounter}" value="" oninput="syncOtherTable()">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Item Name / Description <span class="text-danger">*</span></label>
                    <input type="text" name="garment_other[${otherCounter}][item_name]" class="form-control form-control-sm other-input-name" placeholder="e.g. 24L Polyester 4-Hole Buttons" value="" required oninput="syncOtherTable()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Quantity <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="garment_other[${otherCounter}][quantity]" class="form-control form-control-sm other-input-qty" placeholder="0" value="" required oninput="syncOtherTable()">
                </div>
                <div class="col-md-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">Unit <span class="text-danger">*</span></label>
                        <a href="javascript:void(0)" class="text-decoration-none small text-primary fw-semibold" onclick="promptAddUnit(${otherCounter})">+ Add Unit</a>
                    </div>
                    <select name="garment_other[${otherCounter}][unit]" class="form-select form-select-sm other-input-unit" onchange="onUnitChange(this, ${otherCounter}); syncOtherTable()">
                        ${getUnitOptionsHtml('Pieces')}
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Unit Price (₹) <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text small bg-light text-muted">₹</span>
                        <input type="number" step="0.01" min="0.01" name="garment_other[${otherCounter}][unit_price]" class="form-control form-control-sm other-input-price" placeholder="0.00" value="" required oninput="syncOtherTable()">
                    </div>
                </div>
            </div>
            <!-- Row 2: HSN, Tax Mode, GST & Dynamic Fields -->
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">HSN Code</label>
                        <a href="javascript:void(0)" class="text-decoration-none small text-primary fw-semibold" onclick="promptAddHsn(${otherCounter}, 'other')">+ Add HSN</a>
                    </div>
                    <input type="text" name="garment_other[${otherCounter}][hsn]" class="form-control form-control-sm font-monospace other-input-hsn" data-hsn-autocomplete data-hsn-type="HSN" ${getHsnInputAttributes()} autocomplete="off" oninput="onOtherHsnInput(${otherCounter})" onchange="onOtherHsnChange(${otherCounter})">
                    <small class="hsn-rate-note text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Tax Mode</label>
                    <select name="garment_other[${otherCounter}][tax_mode]" class="form-select form-select-sm other-input-tax-mode" onchange="syncOtherTable()">
                        <option value="Exclude GST" selected>Exclude GST</option>
                        <option value="Include GST">Include GST</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">GST</label>
                    <select name="garment_other[${otherCounter}][gst]" class="form-select form-select-sm other-input-gst" onchange="onOtherGstTypeChange(${otherCounter})">
                        <option value="Exempted" selected>Exempted</option>
                        <option value="Both (CGST and SGST)">Both (CGST and SGST)</option>
                        <option value="SGST">SGST</option>
                        <option value="CGST">CGST</option>
                        <option value="IGST">IGST</option>
                    </select>
                </div>
                <div class="col-md-5 d-none other-gst-dynamic-container" id="otherGstDynamic_${otherCounter}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Tax Input Type</label>
                            <select name="garment_other[${otherCounter}][tax_input_type]" class="form-select form-select-sm other-input-tax-type" onchange="onOtherTaxInputTypeChange(${otherCounter})">
                                <option value="Percentage" selected>Percentage (%)</option>
                                <option value="Amount">Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4 other-sgst-col" id="otherSgstCol_${otherCounter}">
                            <label class="form-label small fw-semibold mb-1 other-sgst-label">SGST (%)</label>
                            <input type="number" step="0.01" min="0" name="garment_other[${otherCounter}][sgst]" class="form-control form-control-sm other-input-sgst" value="" placeholder="0" oninput="syncOtherTable()">
                        </div>
                        <div class="col-md-4 other-cgst-col" id="otherCgstCol_${otherCounter}">
                            <label class="form-label small fw-semibold mb-1 other-cgst-label">CGST (%)</label>
                            <input type="number" step="0.01" min="0" name="garment_other[${otherCounter}][cgst]" class="form-control form-control-sm other-input-cgst" value="" placeholder="0" oninput="syncOtherTable()">
                        </div>
                        <div class="col-md-4 d-none other-igst-col" id="otherIgstCol_${otherCounter}">
                            <label class="form-label small fw-semibold mb-1 other-igst-label">IGST (%)</label>
                            <input type="number" step="0.01" min="0" name="garment_other[${otherCounter}][igst]" class="form-control form-control-sm other-input-igst" value="" placeholder="0" oninput="syncOtherTable()">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.appendChild(div);
    syncOtherTable();
}

function removeOtherRow(id) {
    const card = document.getElementById('otherCard_' + id);
    if (card) {
        card.remove();
        syncOtherTable();
    }
}

function syncOtherTable() {
    const tbody = document.getElementById('otherSuppliesTableBody');
    tbody.innerHTML = '';

    const cards = document.querySelectorAll('#garmentOtherContainer .other-card');
    document.getElementById('otherBadgeCount').textContent = cards.length;
    document.getElementById('otherItemsCountBadge').textContent = cards.length + ' Items';
    document.getElementById('overviewOtherCount').textContent = cards.length;

    if (cards.length === 0) {
        document.getElementById('noSuppliesPlaceholder').classList.remove('d-none');
        tbody.innerHTML = '<tr><td colspan="11" class="text-center py-3 text-muted">Add garment supplies to see their purchase details.</td></tr>';
        document.getElementById('overviewOtherVal').textContent = '₹0.00';
        recalcOverallSummary();
        return;
    }

    document.getElementById('noSuppliesPlaceholder').classList.add('d-none');
    let totalOtherBase = 0;
    let totalOtherTax = 0;

    cards.forEach(function(card, idx) {
        const code = card.querySelector('.other-input-code')?.value || '-';
        const name = card.querySelector('.other-input-name')?.value || '-';
        const hsn = card.querySelector('.other-input-hsn')?.value || '-';
        const qty = parseFloat(card.querySelector('.other-input-qty')?.value) || 0;
        const unit = card.querySelector('.other-input-unit')?.value || 'Pieces';
        const price = parseFloat(card.querySelector('.other-input-price')?.value) || 0;

        const taxInfo = calculateItemTax(card, 'other', qty, price);
        totalOtherBase += taxInfo.base;
        totalOtherTax += taxInfo.tax;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td class="font-monospace fw-semibold text-dark">${code}</td>
            <td class="fw-semibold text-dark text-start">${name}</td>
            <td class="font-monospace">${hsn}</td>
            <td class="fw-semibold">${qty.toFixed(2)}</td>
            <td><span class="badge bg-light text-dark border">${unit}</span></td>
            <td>₹${price.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td class="fw-semibold">₹${taxInfo.base.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td>${taxInfo.badge}</td>
            <td>${taxInfo.taxHtml}</td>
            <td class="table-success fw-bold text-success">₹${taxInfo.total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('overviewOtherVal').textContent = '₹' + (totalOtherBase + totalOtherTax).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    recalcOverallSummary();
}

// ==========================================
// 3. FINISHED GOODS LOGIC (IMAGE 3)
// ==========================================
function addFinishedRow() {
    finishedCounter++;
    document.getElementById('itemsValidationError')?.classList.add('d-none');
    const container = document.getElementById('finishedGoodsContainer');
    document.getElementById('noFinishedPlaceholder').classList.add('d-none');

    const div = document.createElement('div');
    div.className = 'finished-card card border mb-3 shadow-sm rounded-3';
    div.id = 'finishedCard_' + finishedCounter;
    div.innerHTML = `
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-tag text-purple" style="color:#8b5cf6;"></i>
                <span class="fw-bold text-dark font-monospace">GARMENT #${finishedCounter}</span>
                <span class="badge bg-purple-subtle text-purple font-monospace px-2 py-1" style="background:#ede9fe; color:#6d28d9;">FG-0${finishedCounter}</span>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeFinishedRow(${finishedCounter})">
                <i class="bi bi-trash3 me-1"></i> Delete
            </button>
        </div>
        <div class="card-body p-3">
            <!-- Row 1: Product, Qty, Unit Price of Supplier Cost -->
            <div class="row g-2 mb-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold mb-1">Product <span class="text-danger">*</span></label>
                    <input type="text" name="finished_goods[${finishedCounter}][product]" class="form-control form-control-sm text-uppercase fg-input-product" placeholder="e.g. RED SHIRT" value="" required oninput="syncFinishedTable()">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Qty (Pcs) <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="1" name="finished_goods[${finishedCounter}][quantity]" class="form-control form-control-sm fg-input-qty" placeholder="0" value="" required oninput="syncFinishedTable()">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Unit Price of Supplier Cost (₹) <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text small bg-light text-muted">₹</span>
                        <input type="number" step="0.01" min="0.01" name="finished_goods[${finishedCounter}][supplier_cost]" class="form-control form-control-sm fg-input-cost" placeholder="0.00" value="" required oninput="syncFinishedTable()">
                    </div>
                </div>
            </div>

            <!-- Row 2: HSN, Tax Mode, GST & Dynamic Fields -->
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">HSN Code</label>
                        <a href="javascript:void(0)" class="text-decoration-none small text-primary fw-semibold" onclick="promptAddHsn(${finishedCounter}, 'finished')">+ Add HSN</a>
                    </div>
                    <input type="text" name="finished_goods[${finishedCounter}][hsn]" class="form-control form-control-sm font-monospace fg-input-hsn" data-hsn-autocomplete data-hsn-type="HSN" ${getHsnInputAttributes()} autocomplete="off" oninput="onFinishedHsnInput(${finishedCounter})" onchange="onFinishedHsnChange(${finishedCounter})">
                    <small class="hsn-rate-note text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Tax Mode</label>
                    <select name="finished_goods[${finishedCounter}][tax_mode]" class="form-select form-select-sm fg-input-tax-mode" onchange="syncFinishedTable()">
                        <option value="Exclude GST" selected>Exclude GST</option>
                        <option value="Include GST">Include GST</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">GST</label>
                    <select name="finished_goods[${finishedCounter}][gst]" class="form-select form-select-sm fg-input-gst" onchange="onFinishedGstTypeChange(${finishedCounter})">
                        <option value="Exempted" selected>Exempted</option>
                        <option value="Both (CGST and SGST)">Both (CGST and SGST)</option>
                        <option value="SGST">SGST</option>
                        <option value="CGST">CGST</option>
                        <option value="IGST">IGST</option>
                    </select>
                </div>
                <div class="col-md-5 d-none fg-gst-dynamic-container" id="fgGstDynamic_${finishedCounter}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Tax Input Type</label>
                            <select name="finished_goods[${finishedCounter}][tax_input_type]" class="form-select form-select-sm fg-input-tax-type" onchange="onFinishedTaxInputTypeChange(${finishedCounter})">
                                <option value="Percentage" selected>Percentage (%)</option>
                                <option value="Amount">Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4 fg-sgst-col" id="fgSgstCol_${finishedCounter}">
                            <label class="form-label small fw-semibold mb-1 fg-sgst-label">SGST (%)</label>
                            <input type="number" step="0.01" min="0" name="finished_goods[${finishedCounter}][sgst]" class="form-control form-control-sm fg-input-sgst" value="" placeholder="0" oninput="syncFinishedTable()">
                        </div>
                        <div class="col-md-4 fg-cgst-col" id="fgCgstCol_${finishedCounter}">
                            <label class="form-label small fw-semibold mb-1 fg-cgst-label">CGST (%)</label>
                            <input type="number" step="0.01" min="0" name="finished_goods[${finishedCounter}][cgst]" class="form-control form-control-sm fg-input-cgst" value="" placeholder="0" oninput="syncFinishedTable()">
                        </div>
                        <div class="col-md-4 d-none fg-igst-col" id="fgIgstCol_${finishedCounter}">
                            <label class="form-label small fw-semibold mb-1 fg-igst-label">IGST (%)</label>
                            <input type="number" step="0.01" min="0" name="finished_goods[${finishedCounter}][igst]" class="form-control form-control-sm fg-input-igst" value="" placeholder="0" oninput="syncFinishedTable()">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.appendChild(div);
    syncFinishedTable();
}

function removeFinishedRow(id) {
    const card = document.getElementById('finishedCard_' + id);
    if (card) {
        card.remove();
        syncFinishedTable();
    }
}

function syncFinishedTable() {
    const tbody = document.getElementById('finishedGoodsTableBody');
    tbody.innerHTML = '';

    const cards = document.querySelectorAll('#finishedGoodsContainer .finished-card');
    document.getElementById('finishedBadgeCount').textContent = cards.length;
    document.getElementById('finishedItemsCountBadge').textContent = cards.length + ' Items';
    document.getElementById('overviewFinishedCount').textContent = cards.length;

    if (cards.length === 0) {
        document.getElementById('noFinishedPlaceholder').classList.remove('d-none');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3 text-muted">Add finished goods to see inventory details.</td></tr>';
        document.getElementById('overviewFinishedVal').textContent = '₹0.00';
        recalcOverallSummary();
        return;
    }

    document.getElementById('noFinishedPlaceholder').classList.add('d-none');
    let totalFinishedBase = 0;
    let totalFinishedTax = 0;

    cards.forEach(function(card, idx) {
        const product = card.querySelector('.fg-input-product')?.value || '-';
        const hsn = card.querySelector('.fg-input-hsn')?.value || '-';
        const qty = parseInt(card.querySelector('.fg-input-qty')?.value) || 0;
        const cost = parseFloat(card.querySelector('.fg-input-cost')?.value) || 0;

        const taxInfo = calculateItemTax(card, 'fg', qty, cost);
        totalFinishedBase += taxInfo.base;
        totalFinishedTax += taxInfo.tax;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td class="fw-bold text-dark text-start">${escapeHtml(product)}</td>
            <td class="font-monospace">${escapeHtml(hsn)}</td>
            <td class="fw-bold">${qty}</td>
            <td>₹${cost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td>${taxInfo.badge}</td>
            <td>${taxInfo.taxHtml}</td>
            <td class="table-success fw-bold text-success">₹${taxInfo.total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('overviewFinishedVal').textContent = '₹' + (totalFinishedBase + totalFinishedTax).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    recalcOverallSummary();
}

// ==========================================
// 4. OVERALL TOTAL RECALCULATION (IMAGE 2 BOTTOM)
// ==========================================
function recalcOverallSummary() {
    let grandBase = 0;
    let grandTax = 0;

    // Rolls (qty == rq)
    document.querySelectorAll('#rollsContainer .roll-card').forEach(function(card) {
        const rq = parseInt(card.querySelector('.roll-input-rq')?.value) || 1;
        const price = parseFloat(card.querySelector('.roll-input-price')?.value) || 0;
        const taxInfo = calculateRollTax(card, rq, price);

        grandBase += taxInfo.base;
        grandTax += taxInfo.tax;
    });

    // Other Garment Supplies
    document.querySelectorAll('#garmentOtherContainer .other-card').forEach(function(card) {
        const qty = parseFloat(card.querySelector('.other-input-qty')?.value) || 0;
        const price = parseFloat(card.querySelector('.other-input-price')?.value) || 0;
        const taxInfo = calculateItemTax(card, 'other', qty, price);

        grandBase += taxInfo.base;
        grandTax += taxInfo.tax;
    });

    // Finished Goods
    document.querySelectorAll('#finishedGoodsContainer .finished-card').forEach(function(card) {
        const qty = parseInt(card.querySelector('.fg-input-qty')?.value) || 0;
        const cost = parseFloat(card.querySelector('.fg-input-cost')?.value) || 0;
        const taxInfo = calculateItemTax(card, 'fg', qty, cost);

        grandBase += taxInfo.base;
        grandTax += taxInfo.tax;
    });

    const grandTotal = grandBase + grandTax;

    document.getElementById('summarySubtotal').textContent = '₹' + grandBase.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('summaryTaxes').textContent = '₹' + grandTax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('summaryTotalPrice').textContent = '₹' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ==========================================
// 5. LOAD EXISTING ITEMS ON EDIT
// ==========================================
function loadInitialData() {
    if (!isEditMode) return;

    if (Array.isArray(existingRolls) && existingRolls.length > 0) {
        const container = document.getElementById('rollsContainer');
        container.innerHTML = '';
        rollCounter = 0;

        existingRolls.forEach(function(r) {
            rollCounter++;
            const rollCode = generateRollCode(rollCounter, r.item_code);
            const div = document.createElement('div');
            div.className = 'roll-card card border mb-4 shadow-sm rounded-3';
            div.id = 'rollCard_' + rollCounter;

            const existingPhoto = r.photo_path || '';
            let photoPreviewHtml = '';
            if (existingPhoto) {
                const photoUrl = (existingPhoto.startsWith('http://') || existingPhoto.startsWith('https://'))
                    ? existingPhoto
                    : ('<?= \App\Core\Controller::url('/') ?>' + existingPhoto.replace(/^\/+/, ''));
                div.dataset.photoUrl = photoUrl;
                photoPreviewHtml = `
                    <div class="d-inline-flex align-items-center gap-2 p-1 px-2 mt-1 bg-white border rounded shadow-sm">
                        <a href="${photoUrl}" target="_blank" title="View Current Photo">
                            <img src="${photoUrl}" class="rounded border" style="width: 32px; height: 32px; object-fit: cover;" alt="Preview">
                        </a>
                        <span class="small fw-semibold text-truncate text-dark" style="max-width: 180px;">Current Photo</span>
                    </div>
                `;
            }

            const taxMode = r.tax_mode || 'Exclude GST';
            const gstType = r.gst_type || 'Exempted';
            const gstRate = parseFloat(r.gst_rate) || 0;
            const halfRate = (gstRate / 2).toFixed(2);

            const rPat = (r.pattern || r.item_name || '').trim().toUpperCase();
            const rCol = (r.color || '').trim().toUpperCase();
            const rSpecVal = (rPat && rCol) ? `${rPat}|${rCol}` : '';

            const quickSelectHtml = `
                <div class="mb-3 p-2.5 rounded bg-light border d-flex flex-wrap align-items-center justify-content-between gap-2 roll-spec-selector-bar">
                    <div class="d-flex align-items-center gap-1.5 small text-secondary">
                        <i class="bi bi-bookmark-star text-primary"></i>
                        <span class="fw-semibold">Roll Design / Pattern:</span>
                    </div>
                    <div class="flex-grow-1" style="max-width: 520px;">
                        <div class="input-group input-group-sm">
                            <select class="form-select form-select-sm roll-quick-select" id="rollQuickSelect_${rollCounter}" onchange="applySavedRollSpec(this, ${rollCounter})">
                                ${getSavedRollSpecsOptionsHtml(rSpecVal)}
                            </select>
                            <button type="button" class="btn btn-outline-info btn-sm btn-view-spec px-2" id="btnViewSpec_${rollCounter}" onclick="viewCurrentRollSpec(${rollCounter})" title="View Details of Selected Design">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button type="button" class="btn btn-primary btn-sm px-2 fw-semibold" onclick="openCreateDesignModal(${rollCounter})" title="Create New Design & Color">
                                <i class="bi bi-plus-lg"></i> New
                            </button>
                        </div>
                    </div>
                </div>
            `;

            div.innerHTML = `
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-layers text-primary"></i>
                        <span class="fw-bold text-dark font-monospace">ROLL #${rollCounter}</span>
                        <span class="badge bg-dark font-monospace px-2 py-1 roll-code-badge" id="rollCodeBadge_${rollCounter}">${escapeHtml(rollCode)}</span>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeRollRow(${rollCounter})">
                        <i class="bi bi-trash3 me-1"></i> Delete
                    </button>
                </div>
                <div class="card-body p-3">
                    <input type="hidden" name="rolls[${rollCounter}][existing_photo]" value="${escapeHtml(existingPhoto)}">
                    ${quickSelectHtml}
                    <div id="rollStockNotice_${rollCounter}" style="display: none;"></div>
                    <!-- Row 1: Roll No, Color, Pattern, RQ, Weight, Price -->
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Fabric Name <span class="text-danger">*</span></label>
                            <input type="text" name="rolls[${rollCounter}][roll_code]" class="form-control form-control-sm font-monospace text-uppercase roll-input-code" placeholder="E.G. COTTON" value="${escapeHtml(r.item_code || '')}" required oninput="updateRollHeaderCode(${rollCounter}); syncRollsTable()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Color / Shade <span class="text-danger">*</span></label>
                            <input type="text" name="rolls[${rollCounter}][color]" class="form-control form-control-sm text-uppercase roll-input-color" placeholder="E.G. NAVY BLUE" value="${escapeHtml(r.color || '')}" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Design Name / Pattern <span class="text-danger">*</span></label>
                            <input type="text" name="rolls[${rollCounter}][pattern]" class="form-control form-control-sm text-uppercase roll-input-pattern" placeholder="E.G. FLORAL 01" value="${escapeHtml(r.pattern || '')}" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Qty <span class="text-danger">*</span></label>
                            <input type="number" min="1" step="1" name="rolls[${rollCounter}][rq]" class="form-control form-control-sm roll-input-rq" placeholder="1" value="${parseInt(r.rq_qty) || 1}" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Overall Weight (kg) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0.01" name="rolls[${rollCounter}][weight]" class="form-control form-control-sm roll-input-weight" placeholder="0.00" value="${parseFloat(r.net_weight) || ''}" required oninput="syncRollsTable()">
                                <span class="input-group-text small bg-light text-muted">kg</span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Unit Price (₹) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text small bg-light text-muted">₹</span>
                                <input type="number" step="0.01" min="0.01" name="rolls[${rollCounter}][price]" class="form-control form-control-sm roll-input-price" placeholder="0.00" value="${parseFloat(r.unit_price) || ''}" required oninput="syncRollsTable()">
                            </div>
                        </div>
                    </div>
                    <!-- Row 2: HSN, Tax Mode, GST & Dynamic Fields -->
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-md-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0">HSN Code</label>
                                <a href="javascript:void(0)" class="text-decoration-none small text-primary fw-semibold" onclick="promptAddHsn(${rollCounter})">+ Add HSN</a>
                            </div>
                            <input type="text" name="rolls[${rollCounter}][hsn]" class="form-control form-control-sm font-monospace roll-input-hsn" value="${escapeHtml(r.hsn_code || '')}" data-hsn-autocomplete data-hsn-type="HSN" ${getHsnInputAttributes(r.hsn_code || '')} autocomplete="off" oninput="onRollHsnInput(${rollCounter})" onchange="onRollHsnChange(${rollCounter})">
                            <small class="hsn-rate-note text-muted d-block mt-1"></small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Tax Mode</label>
                            <select name="rolls[${rollCounter}][tax_mode]" class="form-select form-select-sm roll-input-tax-mode" onchange="syncRollsTable()">
                                <option value="Exclude GST" ${taxMode === 'Exclude GST' ? 'selected' : ''}>Exclude GST</option>
                                <option value="Include GST" ${taxMode === 'Include GST' ? 'selected' : ''}>Include GST</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">GST</label>
                            <select name="rolls[${rollCounter}][gst]" class="form-select form-select-sm roll-input-gst" onchange="onRollGstTypeChange(${rollCounter})">
                                <option value="Exempted" ${gstType === 'Exempted' ? 'selected' : ''}>Exempted</option>
                                <option value="Both (CGST and SGST)" ${gstType === 'Both (CGST and SGST)' ? 'selected' : ''}>Both (CGST and SGST)</option>
                                <option value="SGST" ${gstType === 'SGST' ? 'selected' : ''}>SGST</option>
                                <option value="CGST" ${gstType === 'CGST' ? 'selected' : ''}>CGST</option>
                                <option value="IGST" ${gstType === 'IGST' ? 'selected' : ''}>IGST</option>
                            </select>
                        </div>
                        <div class="col-md-5 ${gstType === 'Exempted' ? 'd-none' : ''} roll-gst-dynamic-container" id="gstDynamic_${rollCounter}">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Tax Input Type</label>
                                    <select name="rolls[${rollCounter}][tax_input_type]" class="form-select form-select-sm roll-input-tax-type" onchange="onRollTaxInputTypeChange(${rollCounter})">
                                        <option value="Percentage" selected>Percentage (%)</option>
                                        <option value="Amount">Amount (₹)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 roll-sgst-col ${gstType === 'CGST' || gstType === 'IGST' ? 'd-none' : ''}" id="sgstCol_${rollCounter}">
                                    <label class="form-label small fw-semibold mb-1 roll-sgst-label">SGST (%)</label>
                                    <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][sgst]" class="form-control form-control-sm roll-input-sgst" value="${gstType === 'Both (CGST and SGST)' ? halfRate : (gstType === 'SGST' ? gstRate : 0)}" placeholder="0" oninput="syncRollsTable()">
                                </div>
                                <div class="col-md-4 roll-cgst-col ${gstType === 'SGST' || gstType === 'IGST' ? 'd-none' : ''}" id="cgstCol_${rollCounter}">
                                    <label class="form-label small fw-semibold mb-1 roll-cgst-label">CGST (%)</label>
                                    <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][cgst]" class="form-control form-control-sm roll-input-cgst" value="${gstType === 'Both (CGST and SGST)' ? halfRate : (gstType === 'CGST' ? gstRate : 0)}" placeholder="0" oninput="syncRollsTable()">
                                </div>
                                <div class="col-md-4 roll-igst-col ${gstType !== 'IGST' ? 'd-none' : ''}" id="igstCol_${rollCounter}">
                                    <label class="form-label small fw-semibold mb-1 roll-igst-label">IGST (%)</label>
                                    <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][igst]" class="form-control form-control-sm roll-input-igst" value="${gstType === 'IGST' ? gstRate : 0}" placeholder="0" oninput="syncRollsTable()">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Row 3: Roll Sample Photo & Preview -->
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1">Roll Sample Photo (Optional — JPG, PNG, WEBP)</label>
                            <input type="file" name="rolls[${rollCounter}][photo]" class="form-control form-control-sm roll-input-photo" accept=".jpg,.jpeg,.png,.webp" onchange="previewRollPhoto(this, ${rollCounter})">
                            <div class="roll-photo-preview-inline mt-1" id="photoPreview_${rollCounter}">${photoPreviewHtml}</div>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(div);
            onRollHsnChange(rollCounter);
        });
    }

    if (Array.isArray(existingOther) && existingOther.length > 0) {
        existingOther.forEach(function(g) {
            addOtherRow();
            const card = document.getElementById('otherCard_' + otherCounter);
            if (card) {
                if (card.querySelector('.other-input-code')) card.querySelector('.other-input-code').value = g.item_code || '';
                if (card.querySelector('.other-input-name')) card.querySelector('.other-input-name').value = g.item_name || '';
                const hsnInput = card.querySelector('.other-input-hsn');
                if (hsnInput) {
                    hsnInput.value = g.hsn_code || '';
                    setHsnInputMetadata(hsnInput, hsnInput.value);
                }
                if (card.querySelector('.other-input-qty')) card.querySelector('.other-input-qty').value = parseFloat(g.qty) || 1;
                if (card.querySelector('.other-input-unit')) card.querySelector('.other-input-unit').value = g.unit || 'Pieces';
                if (card.querySelector('.other-input-price')) card.querySelector('.other-input-price').value = parseFloat(g.unit_price) || 0;
                if (card.querySelector('.other-input-tax-mode')) card.querySelector('.other-input-tax-mode').value = g.tax_mode || 'Exclude GST';
                if (card.querySelector('.other-input-gst')) card.querySelector('.other-input-gst').value = g.gst_type || 'Exempted';
                if (card.querySelector('.other-input-tax-type')) card.querySelector('.other-input-tax-type').value = g.tax_input_type || 'Percentage';
                if (card.querySelector('.other-input-sgst')) card.querySelector('.other-input-sgst').value = parseFloat(g.sgst) || 0;
                if (card.querySelector('.other-input-cgst')) card.querySelector('.other-input-cgst').value = parseFloat(g.cgst) || 0;
                if (card.querySelector('.other-input-igst')) card.querySelector('.other-input-igst').value = parseFloat(g.igst) || 0;
                onOtherHsnChange(otherCounter);
            }
        });
    }

    if (Array.isArray(existingFinished) && existingFinished.length > 0) {
        existingFinished.forEach(function(f) {
            addFinishedRow();
            const card = document.getElementById('finishedCard_' + finishedCounter);
            if (card) {
                if (card.querySelector('.fg-input-product')) card.querySelector('.fg-input-product').value = f.item_name || '';
                if (card.querySelector('.fg-input-category')) card.querySelector('.fg-input-category').value = f.category_name || '';
                if (card.querySelector('.fg-input-type')) card.querySelector('.fg-input-type').value = f.product_type || 'Individual Piece';
                if (card.querySelector('.fg-input-variant')) card.querySelector('.fg-input-variant').value = f.specification || 'M';
                if (card.querySelector('.fg-input-sku')) card.querySelector('.fg-input-sku').value = f.item_code || '';
                const hsnInput = card.querySelector('.fg-input-hsn');
                if (hsnInput) {
                    hsnInput.value = f.hsn_code || '';
                    setHsnInputMetadata(hsnInput, hsnInput.value);
                }
                if (card.querySelector('.fg-input-qty')) card.querySelector('.fg-input-qty').value = parseInt(f.qty) || 1;
                if (card.querySelector('.fg-input-cost')) card.querySelector('.fg-input-cost').value = parseFloat(f.unit_price) || 0;
                if (card.querySelector('.fg-input-mrp')) card.querySelector('.fg-input-mrp').value = parseFloat(f.mrp) || '';
                if (card.querySelector('.fg-input-selling')) card.querySelector('.fg-input-selling').value = parseFloat(f.selling_price) || '';
                if (card.querySelector('.fg-input-tax-mode')) card.querySelector('.fg-input-tax-mode').value = f.tax_mode || 'Exclude GST';
                if (card.querySelector('.fg-input-gst')) card.querySelector('.fg-input-gst').value = f.gst_type || 'Exempted';
                if (card.querySelector('.fg-input-tax-type')) card.querySelector('.fg-input-tax-type').value = f.tax_input_type || 'Percentage';
                if (card.querySelector('.fg-input-sgst')) card.querySelector('.fg-input-sgst').value = parseFloat(f.sgst) || 0;
                if (card.querySelector('.fg-input-cgst')) card.querySelector('.fg-input-cgst').value = parseFloat(f.cgst) || 0;
                if (card.querySelector('.fg-input-igst')) card.querySelector('.fg-input-igst').value = parseFloat(f.igst) || 0;
                onFinishedHsnChange(finishedCounter);
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (isEditMode) {
        loadInitialData();
    }
    syncRollsTable();
    syncOtherTable();
    syncFinishedTable();

    // Keep validation messages beside the fields that need attention.
    const form = document.getElementById('formPurchaseBill');
    const getFieldLabel = function (input) {
        const fieldColumn = input.closest('.col, [class*="col-"]');
        const label = fieldColumn ? fieldColumn.querySelector('label') : null;
        return (label ? label.textContent : input.name)
            .replace(/\*/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    };
    const showFieldError = function (input) {
        const anchor = input.closest('.input-group') || input;
        let feedback = anchor.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback purchase-field-feedback';
            anchor.insertAdjacentElement('afterend', feedback);
        }

        const label = getFieldLabel(input);
        if (input.validity.valueMissing) {
            feedback.textContent = `${label} is required.`;
        } else if (input.validity.rangeUnderflow) {
            feedback.textContent = `${label} must be at least ${input.min}.`;
        } else if (input.validity.stepMismatch || input.validity.badInput) {
            feedback.textContent = `Enter a valid ${label.toLowerCase()}.`;
        } else if (input.validity.customError) {
            feedback.textContent = input.validationMessage;
        } else {
            feedback.textContent = `Enter a valid ${label.toLowerCase()}.`;
        }

        input.classList.add('is-invalid');
        feedback.classList.remove('d-none');
        feedback.classList.add('d-block');
    };
    const clearFieldError = function (input) {
        input.classList.remove('is-invalid');
        const anchor = input.closest('.input-group') || input;
        const feedback = anchor.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.classList.remove('d-block');
            feedback.classList.add('d-none');
        }
    };
    const attachmentsInput = form.querySelector('input[name="attachments[]"]');
    const hasExistingAttachments = <?= (!empty($existingAttachments)) ? 'true' : 'false' ?>;
    const validateAttachments = function () {
        if (!attachmentsInput) return;

        const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        const selectedFiles = Array.from(attachmentsInput.files || []);
        if (selectedFiles.length === 0 && !hasExistingAttachments) {
            attachmentsInput.setCustomValidity('Please upload at least one tax invoice / bill attachment file.');
            return;
        }
        const invalidType = selectedFiles.some(function (file) {
            return !allowedExtensions.includes(file.name.split('.').pop().toLowerCase());
        });
        const oversizedFile = selectedFiles.some(function (file) {
            return file.size > 5 * 1024 * 1024;
        });

        attachmentsInput.setCustomValidity(
            invalidType
                ? 'Attachments must be JPG, PNG, WEBP, or PDF files.'
                : (oversizedFile ? 'Each attachment must be no larger than 5 MB.' : '')
        );
    };

    form.addEventListener('submit', function (event) {
        validateAttachments();
        const invalidFields = Array.from(form.elements).filter(function (control) {
            return control.willValidate && !control.checkValidity();
        });
        const hasItem = form.querySelector('.roll-card, .other-card, .finished-card');
        const itemsError = document.getElementById('itemsValidationError');

        invalidFields.forEach(showFieldError);

        if (!hasItem && itemsError) {
            itemsError.classList.remove('d-none');
        } else if (itemsError) {
            itemsError.classList.add('d-none');
        }

        if (invalidFields.length > 0 || !hasItem) {
            event.preventDefault();
            event.stopPropagation();
            const firstInvalid = invalidFields[0];
            if (firstInvalid) {
                const pane = firstInvalid.closest('.tab-pane');
                if (pane && pane.id === 'paneRolls') document.getElementById('tabRollsBtn').click();
                if (pane && pane.id === 'paneOther') document.getElementById('tabOtherBtn').click();
                if (pane && pane.id === 'paneFinished') document.getElementById('tabFinishedBtn').click();
                setTimeout(() => firstInvalid.focus(), 150);
            }
        }
        form.classList.add('was-validated');
    }, false);

    form.addEventListener('input', function (event) {
        const input = event.target;
        if (input && typeof input.checkValidity === 'function' && input.checkValidity()) {
            clearFieldError(input);
        }
    });
    form.addEventListener('change', function (event) {
        const input = event.target;
        if (input === attachmentsInput) validateAttachments();
        if (input && typeof input.checkValidity === 'function' && input.checkValidity()) {
            clearFieldError(input);
        }
    });
});
</script>
