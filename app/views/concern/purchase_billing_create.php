<?php
$isEdit = !empty($bill);
$formAction = $formAction ?? ($isEdit ? \App\Core\Controller::url('/m/purchase_billing/update') : \App\Core\Controller::url('/m/purchase_billing/store'));
$hsnSaveUrl = $hsnSaveUrl ?? \App\Core\Controller::url('/m/purchase_billing/save_hsn');
$rollSpecSaveUrl = $rollSpecSaveUrl ?? \App\Core\Controller::url('/m/purchase_billing/save_roll_spec');
$quickSupplierSaveUrl = $quickSupplierSaveUrl ?? '';
$quickSupplierUpdateUrl = $quickSupplierUpdateUrl ?? '';
$quickSupplierDeleteUrl = $quickSupplierDeleteUrl ?? '';
$quickCategorySaveUrl = $quickCategorySaveUrl ?? '';
$quickAttributeSaveUrl = $quickAttributeSaveUrl ?? '';
$invoiceCheckUrl = $invoiceCheckUrl ?? '';
$isProductAddingForm = ($currentModule ?? '') === 'product_adding';
$supplierIsOptional = $isProductAddingForm;
$backUrl = $backUrl ?? \App\Core\Controller::url('/m/purchase_billing');
$backLabel = $backLabel ?? 'Back to Purchases';
$submitLabel = $submitLabel ?? ($isEdit ? 'Update Purchase Bill' : 'Save Purchase');
$pageHeading = $pageHeading ?? ($isEdit ? ('Edit Purchase Bill: #' . htmlspecialchars($bill['bill_no'])) : 'Record Production Purchase');
$pageDescription = $pageDescription ?? ($isEdit ? 'Update fabric rolls, garment supplies/accessories and finished goods for this bill.' : 'Record fabric rolls, garment supplies/accessories and finished goods received from vendors.');
$currentPaymentMethod = strtolower(str_replace([' ', '-'], '_', trim((string) ($bill['payment_method'] ?? 'credit'))));
$paymentMethods = [
    'credit' => 'Credit',
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'upi' => 'UPI',
];
$existingAttachments = [];
$savedBillAttachments = [];
if (!empty($savedBill)) {
    $decodedBillAttachments = json_decode((string) ($savedBill['attachment_paths'] ?? ''), true);
    if (is_array($decodedBillAttachments)) {
        $savedBillAttachments = array_values(array_filter($decodedBillAttachments, static function ($path): bool {
            return is_string($path) && trim($path) !== '';
        }));
    }
    if (empty($savedBillAttachments) && !empty($savedBill['attachment_path'])) {
        $savedBillAttachments[] = (string) $savedBill['attachment_path'];
    }
}
if ($isEdit) {
    $decodedAttachments = json_decode((string) ($bill['attachment_paths'] ?? ''), true);
    if (is_array($decodedAttachments)) {
        $existingAttachments = array_values(array_filter($decodedAttachments, static function ($path): bool {
            return is_string($path) && trim($path) !== '';
        }));
    }
    if (empty($existingAttachments) && !empty($bill['attachment_path'])) {
        $existingAttachments[] = (string) $bill['attachment_path'];
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
<?php if ($isProductAddingForm): ?>
<style>
.product-adding-bulk-checkbox {
    width: 18px;
    height: 18px;
    margin: 0;
    cursor: pointer;
    border: 2px solid #64748b;
    flex-shrink: 0;
}
.product-adding-bulk-checkbox:checked {
    border-color: #0d6efd;
}
.fg-variant-matrix-scroll {
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-gutter: stable;
}
.fg-variant-matrix {
    min-width: 1750px;
    table-layout: auto;
    border-color: #cbd5e1;
}
.fg-variant-matrix thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    min-width: 105px;
    padding: .65rem .55rem;
    background: #eaf0f8;
    color: #334155;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .035em;
    line-height: 1.25;
    white-space: normal;
    vertical-align: middle;
}
.fg-variant-matrix tbody td {
    min-width: 105px;
    padding: .55rem;
    background: #fff;
    vertical-align: middle;
}
.fg-variant-matrix tbody tr:hover td {
    background: #f8fbff;
}
.fg-variant-matrix .fg-variant-name {
    min-width: 165px;
}
.fg-variant-matrix .fg-variant-category,
.fg-variant-matrix .fg-variant-subcategory,
.fg-variant-matrix .fg-variant-sub-subcategory {
    min-width: 125px;
}
.fg-variant-hierarchy-control {
    display: flex;
    align-items: center;
    gap: .3rem;
    min-width: 160px;
}
.fg-variant-hierarchy-control select {
    min-width: 0;
    flex: 1 1 auto;
}
.fg-variant-hierarchy-control .btn {
    flex: 0 0 30px;
    width: 30px;
    padding: .2rem;
}
.fg-variant-attribute-control {
    display: flex;
    align-items: center;
    gap: .3rem;
    min-width: 130px;
}
.fg-variant-attribute-control input,
.fg-variant-attribute-control select {
    min-width: 0;
    flex: 1 1 auto;
}
.fg-variant-attribute-control .btn {
    flex: 0 0 30px;
    width: 30px;
    padding: .2rem;
}
.fg-variant-matrix .fg-variant-hsn {
    min-width: 105px;
}
.fg-variant-matrix .fg-variant-gst-rate {
    min-width: 80px;
    background: #f1f5f9;
    font-weight: 700;
    text-align: right;
}
.fg-attribute-option-list {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    max-height: 5.5rem;
    overflow-y: auto;
    padding: .15rem;
}
.fg-attribute-option {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .3rem .55rem;
    margin: 0;
    border: 1px solid #d5deea;
    border-radius: .45rem;
    background: #fff;
    cursor: pointer;
    font-size: .82rem;
    line-height: 1.2;
}
.fg-attribute-option.is-selected {
    background: #eef2ff;
    border-color: #818cf8;
    color: #3730a3;
}
.fg-attribute-option .form-check-input {
    margin: 0;
}
.fg-quick-fill-panel {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}
.fg-quick-fill-panel .form-control {
    min-width: 0;
}
.fg-quick-fill-grid {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    overflow: visible;
}
.fg-quick-fill-grid > div:not(.d-none) {
    flex: 1 1 0;
    min-width: 82px;
}
.fg-quick-fill-label {
    display: flex;
    align-items: center;
    gap: .25rem;
    min-height: 1.2rem;
    margin-bottom: .25rem;
    color: #475569;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
@media (max-width: 767.98px) {
    .fg-variant-matrix-scroll {
        max-height: 70vh;
        overflow: auto;
    }
}
#rollsEntryTable td.roll-photo-td {
    overflow: hidden;
    max-width: 0;
}
.roll-photo-upload-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    width: 100%;
    max-width: 100%;
    height: 38px;
    padding: 0 0.35rem;
    border: 1.5px dashed #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.15s ease;
    margin: 0;
}
.roll-photo-upload-btn:hover {
    border-color: #4f46e5;
    background: #eef2ff;
    color: #4f46e5;
}
.roll-photo-preview-inline {
    width: 100%;
    max-width: 100%;
}
.roll-photo-preview-chip {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    width: 100%;
    max-width: 100%;
    height: 38px;
    padding: 2px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
}
@media (max-width: 1150px) {
    .roll-photo-upload-btn span {
        display: none;
    }
}
.roll-photo-thumb-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    border-radius: 5px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: #fff;
}
.roll-photo-thumb-link img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.roll-photo-action-btn {
    width: 20px;
    height: 20px;
    padding: 0;
    margin: 0;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    font-size: 0.7rem;
    line-height: 1;
    cursor: pointer;
    transition: all 0.15s ease;
}
.roll-photo-change-btn {
    background: #ffffff;
    color: #475569;
    border: 1px solid #cbd5e1;
}
.roll-photo-change-btn:hover {
    border-color: #4f46e5;
    color: #4f46e5;
    background: #eef2ff;
}
.roll-photo-clear-btn {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.roll-photo-clear-btn:hover {
    background: #fee2e2;
    border-color: #f87171;
    color: #b91c1c;
}
.roll-delete-btn {
    width: 30px;
    height: 30px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
}
</style>
<?php endif; ?>
<div class="container-fluid px-0">

    <?php if ($isProductAddingForm && !empty($savedBill)): ?>
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-receipt text-primary me-2"></i>Purchase &amp; Supplier Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Target concern</div>
                        <div class="fw-semibold"><?= htmlspecialchars($savedBill['concern_name'] ?? '—') ?></div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Supplier</div>
                        <div class="fw-semibold"><?= htmlspecialchars($savedBill['supplier_name'] ?? '—') ?></div>
                        <?php if (!empty($savedBill['supplier_company'])): ?>
                            <div class="small text-muted"><?= htmlspecialchars($savedBill['supplier_company']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Supplier invoice</div>
                        <div class="fw-semibold"><?= htmlspecialchars($savedBill['supplier_invoice_no'] ?? '—') ?></div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Purchase date</div>
                        <div class="fw-semibold">
                            <?= !empty($savedBill['bill_date']) ? htmlspecialchars(date('d M Y', strtotime((string) $savedBill['bill_date']))) : '—' ?>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Payment status</div>
                        <div class="fw-semibold"><?= htmlspecialchars(ucfirst((string) ($savedBill['payment_status'] ?? 'unpaid'))) ?></div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Payment method</div>
                        <div class="fw-semibold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($savedBill['payment_method'] ?? 'credit')))) ?></div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Paid amount</div>
                        <div class="fw-semibold">₹<?= number_format((float) ($savedBill['paid_amount'] ?? 0), 2) ?></div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Stock status</div>
                        <div class="fw-semibold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($savedBill['inward_status'] ?? 'pending')))) ?></div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Purchase amount</div>
                        <div class="fw-semibold">₹<?= number_format((float) ($savedBill['total_amount'] ?? 0), 2) ?></div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Tax amount</div>
                        <div class="fw-semibold">₹<?= number_format((float) ($savedBill['tax_amount'] ?? 0), 2) ?></div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="small text-muted">Net amount</div>
                        <div class="fw-semibold text-primary">₹<?= number_format((float) ($savedBill['net_amount'] ?? 0), 2) ?></div>
                    </div>
                    <?php if ($savedBillAttachments): ?>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="small text-muted">Invoice attachments</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <?php foreach ($savedBillAttachments as $index => $attachmentPath): ?>
                                    <a href="<?= htmlspecialchars(\App\Services\CloudinaryService::url($attachmentPath)) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-paperclip me-1"></i>Attachment <?= $index + 1 ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($savedBill['notes'] ?? '')) !== ''): ?>
                        <div class="col-12">
                            <div class="small text-muted">Purchase notes</div>
                            <div><?= nl2br(htmlspecialchars((string) $savedBill['notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) ($savedBill['fabric_description'] ?? '')) !== ''): ?>
                        <div class="col-12">
                            <div class="small text-muted">Purchase description</div>
                            <div><?= nl2br(htmlspecialchars((string) $savedBill['fabric_description'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <style>
            #productAddingBulkLabelTable > :not(caption) > * > * {
                padding: 0.35rem 0.5rem;
                vertical-align: middle;
            }
            #productAddingBulkLabelTable tbody .badge {
                font-size: 0.70rem;
            }
        </style>
        <section class="card border-0 shadow-sm rounded-3 mb-4">
            <?php if (empty($savedBillItems)): ?>
                <div class="card-body text-muted">This saved bill has no printable lines.</div>
            <?php else: ?>
                <form action="<?= $productAddingLabelUrl ?>" method="POST" target="productAddingLabelPreviewFrame" id="productAddingBulkLabelForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="bill_id" value="<?= (int) $savedBill['id'] ?>">
                    <input type="hidden" name="inline_preview" value="1">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-table text-primary fs-6"></i>
                            <h6 class="mb-0 fw-bold text-dark">Bill Items &amp; Label Generation (<?= count($savedBillItems) ?>)</h6>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1" id="productAddingSelectAllLines">
                            Select all
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0 small" id="productAddingBulkLabelTable">
                            <thead class="table-light text-uppercase text-secondary" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                                <tr>
                                    <th class="text-center" style="width: 44px;">Bulk</th>
                                    <th>Item</th>
                                    <th class="text-center">Type</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-center">Label Progress</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($savedBillItems as $savedItem): ?>
                                    <?php
                                    $savedItemId = (int) $savedItem['id'];
                                    $itemSection = (string) $savedItem['section_type'];
                                    $isSavedItemInStock = ($savedItem['inward_status'] ?? 'pending') === 'completed';
                                    $requiredLabelQty = (int) $savedItem['required_label_qty'];
                                    $generatedLabelQty = (int) $savedItem['generated_label_qty'];
                                    $defaultLabelQty = (int) $savedItem['default_label_qty'];
                                    $purchaseQty = $savedItem['purchase_qty_original'] ?? $savedItem['qty'];
                                    $stockedQty = (float) $savedItem['stocked_qty'];
                                    $needsStockCorrection = $isSavedItemInStock &&
                                        round($stockedQty, 2) < round((float) $purchaseQty, 2);
                                    ?>
                                    <tr>
                                        <td class="text-center align-middle">
                                            <input
                                                class="form-check-input m-0 product-adding-bulk-checkbox"
                                                type="checkbox"
                                                name="item_ids[]"
                                                value="<?= $savedItemId ?>"
                                                data-labels-complete="<?= $savedItem['labels_complete'] ? '1' : '0' ?>"
                                                data-in-stock="<?= $isSavedItemInStock ? '1' : '0' ?>"
                                                data-item-name="<?= htmlspecialchars($savedItem['item_name'] ?: 'Purchase item') ?>"
                                                data-item-section="<?= htmlspecialchars(ucwords(str_replace('_', ' ', $itemSection))) ?>"
                                                data-default-label-count="<?= $defaultLabelQty ?>"
                                                data-generated-label-count="<?= $generatedLabelQty ?>"
                                                data-required-label-count="<?= $requiredLabelQty ?>"
                                                data-purchase-qty="<?= htmlspecialchars((string) $purchaseQty) ?>"
                                                data-stock-qty="<?= htmlspecialchars((string) $stockedQty) ?>"
                                                <?= $isSavedItemInStock ? '' : 'checked' ?>
                                                aria-label="Select <?= htmlspecialchars($savedItem['item_name']) ?> for bulk actions">
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($savedItem['item_name'] ?: 'Purchase item') ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($savedItem['item_code'] ?? '') ?>
                                                <?php if (!empty($savedItem['hsn_code'])): ?> · HSN <?= htmlspecialchars($savedItem['hsn_code']) ?><?php endif; ?>
                                            </div>
                                            <?php
                                            $attrParts = [];
                                            $vAttrsRaw = $savedItem['variant_attributes'] ?? '';
                                            $vAttrs = is_string($vAttrsRaw) && $vAttrsRaw !== '' ? json_decode($vAttrsRaw, true) : (is_array($vAttrsRaw) ? $vAttrsRaw : []);
                                            if (!is_array($vAttrs)) $vAttrs = [];
                                            $itemSize = trim((string)($savedItem['variant_size'] ?? ($savedItem['size'] ?? ($vAttrs['size'] ?? ($vAttrs['Size'] ?? '')))));
                                            $itemColor = trim((string)($savedItem['color'] ?? ($vAttrs['color'] ?? ($vAttrs['Color'] ?? ''))));
                                            if ($itemSize !== '') $attrParts[] = 'Size: ' . $itemSize;
                                            if ($itemColor !== '') $attrParts[] = 'Color: ' . $itemColor;
                                            if (!empty($savedItem['pattern'])) $attrParts[] = 'Pattern: ' . trim((string)$savedItem['pattern']);
                                            foreach ($vAttrs as $ak => $av) {
                                                $akNorm = strtolower(trim((string)$ak));
                                                if (in_array($akNorm, ['size', 'color'], true)) continue;
                                                $avStr = is_array($av) ? implode(', ', $av) : trim((string)$av);
                                                if ($avStr !== '') {
                                                    $attrParts[] = ucwords(str_replace('_', ' ', (string)$ak)) . ': ' . $avStr;
                                                }
                                            }
                                            ?>
                                            <?php if (!empty($attrParts)): ?>
                                                <div class="small text-primary-emphasis mt-1">
                                                    <?= htmlspecialchars(implode(' · ', $attrParts)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                Unit ₹<?= number_format((float) ($savedItem['unit_price'] ?? 0), 2) ?>
                                                · Line ₹<?= number_format((float) ($savedItem['total_price'] ?? 0), 2) ?>
                                            </div>
                                        </td>
                                        <td class="text-center"><span class="badge text-bg-light border"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $itemSection))) ?></span></td>
                                        <td class="text-end">
                                            <?= htmlspecialchars((string) $purchaseQty) ?> <?= htmlspecialchars($savedItem['unit'] ?? '') ?>
                                            <?php if ($isSavedItemInStock): ?>
                                                <div class="small text-success">Stocked: <?= htmlspecialchars((string) $stockedQty) ?> <?= htmlspecialchars($savedItem['unit'] ?? '') ?></div>
                                            <?php else: ?>
                                                <div class="small text-primary">Stock qty: <?= htmlspecialchars((string) $purchaseQty) ?> <?= htmlspecialchars($savedItem['unit'] ?? '') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($savedItem['labels_complete']): ?>
                                                <span id="productAddingLabelProgress<?= $savedItemId ?>" class="badge text-bg-success">
                                                    <?= $generatedLabelQty ?> labels
                                                    <?= $isSavedItemInStock
                                                        ? ' · Stocked ' . htmlspecialchars((string) $stockedQty)
                                                        : ' · Stock qty ' . htmlspecialchars((string) $purchaseQty) ?>
                                                </span>
                                            <?php else: ?>
                                                <span id="productAddingLabelProgress<?= $savedItemId ?>" class="badge text-bg-warning">No labels printed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-label-form-id="productAddingLabelForm<?= $savedItemId ?>"
                                                data-label-item-id="<?= $savedItemId ?>"
                                                data-label-item-name="<?= htmlspecialchars($savedItem['item_name'] ?: 'Purchase item') ?>"
                                                data-label-item-section="<?= htmlspecialchars(ucwords(str_replace('_', ' ', $itemSection))) ?>"
                                                data-default-label-count="<?= $defaultLabelQty ?>">
                                                <i class="bi bi-printer me-1"></i>Print labels
                                            </button>
                                            <?php if ($isSavedItemInStock): ?>
                                                <span class="badge text-bg-success ms-1">In stock</span>
                                            <?php elseif ($savedItem['labels_complete']): ?>
                                                <button type="submit" form="productAddingStockForm<?= $savedItemId ?>" class="btn btn-sm btn-success ms-1" data-product-adding-stock-button="<?= $savedItemId ?>">
                                                    <i class="bi bi-box-arrow-in-down me-1"></i>Add stock
                                                </button>
                                            <?php else: ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary ms-1"
                                                    data-product-adding-stock-button="<?= $savedItemId ?>"
                                                    data-stock-form-id="productAddingStockForm<?= $savedItemId ?>"
                                                    disabled
                                                    title="Generate enough labels first">Add stock</button>
                                            <?php endif; ?>
                                            <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('product_adding', 'edit') || \App\Core\Auth::can('purchase_billing', 'edit')): ?>
                                                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/edit?id=' . (int) $savedBill['id']) ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Edit bill">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="small text-muted">Label count controls printed copies only; the full bill quantity is added to stock. Each line can be added once.</span>
                        <div class="d-flex flex-wrap gap-2">
                            <span id="productAddingBulkLabelFeedback" class="small text-danger align-self-center d-none" role="alert"></span>
                            <button
                                type="submit"
                                id="productAddingBulkStockButton"
                                form="productAddingBulkStockForm"
                                class="btn btn-success"
                                disabled
                                title="Print at least one label for a pending line first">
                                <i class="bi bi-box-arrow-in-down me-1"></i>Add selected to stock
                            </button>
                            <button type="button" class="btn btn-primary" id="productAddingBulkLabelButton">
                                <i class="bi bi-printer-fill me-1"></i>Print selected lines
                            </button>
                        </div>
                    </div>
                </form>
                <form id="productAddingBulkStockForm" action="<?= $productAddingStockUrl ?>" method="POST" class="d-none">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="bill_id" value="<?= (int) $savedBill['id'] ?>">
                    <input type="hidden" name="product_adding" value="1">
                </form>
                <?php foreach ($savedBillItems as $savedItem): ?>
                    <?php
                    $savedItemId = (int) $savedItem['id'];
                    $itemTab = $savedItem['section_type'] === 'finished_goods'
                        ? 'finished_goods'
                        : ($savedItem['section_type'] === 'rolls' ? 'rolls' : 'garment_other');
                    ?>
                    <form
                        id="productAddingLabelForm<?= $savedItemId ?>"
                        action="<?= $productAddingLabelUrl ?>"
                        method="POST"
                        target="productAddingLabelPreviewFrame"
                        class="d-none"
                        data-label-item-id="<?= $savedItemId ?>"
                        data-label-item-name="<?= htmlspecialchars($savedItem['item_name'] ?: 'Purchase item') ?>"
                        data-label-item-section="<?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $savedItem['section_type']))) ?>"
                        data-default-label-count="<?= (int) $savedItem['default_label_qty'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="bill_id" value="<?= (int) $savedBill['id'] ?>">
                        <input type="hidden" name="item_id" value="<?= $savedItemId ?>">
                        <input type="hidden" name="inline_preview" value="1">
                    </form>
                    <form id="productAddingStockForm<?= $savedItemId ?>" action="<?= $productAddingStockUrl ?>" method="POST" class="d-none">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="bill_id" value="<?= (int) $savedBill['id'] ?>">
                        <input type="hidden" name="item_id" value="<?= $savedItemId ?>">
                        <input type="hidden" name="tab" value="<?= $itemTab ?>">
                        <input type="hidden" name="product_adding" value="1">
                    </form>
                    <form id="productAddingStockCorrectionForm<?= $savedItemId ?>" action="<?= $productAddingCorrectionUrl ?>" method="POST" class="d-none">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="bill_id" value="<?= (int) $savedBill['id'] ?>">
                        <input type="hidden" name="item_id" value="<?= $savedItemId ?>">
                    </form>
                <?php endforeach; ?>
                <div class="card-footer bg-light d-none" id="productAddingLabelPreviewPanel">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="fw-semibold"><i class="bi bi-printer text-primary me-1"></i>Generated labels</span>
                        <span class="small text-muted">Print from the preview below; it will remain on this Product Adding page.</span>
                    </div>
                    <iframe
                        id="productAddingLabelPreviewFrame"
                        name="productAddingLabelPreviewFrame"
                        title="Product Adding label preview"
                        class="w-100 bg-white border rounded"
                        style="min-height: 640px; height: 640px;"></iframe>
                </div>
            <?php endif; ?>
        </section>
        <div class="modal fade" id="productAddingLabelCountModal" tabindex="-1" aria-labelledby="productAddingLabelCountTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="productAddingLabelCountTitle">Choose label counts</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3 p-3 border rounded-3 bg-light">
                            <label for="productAddingBrandInput" class="form-label small fw-bold text-uppercase text-secondary mb-2">Brand Name (Optional)</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="productAddingBrandInput" placeholder="Enter or search brand name..." autocomplete="off" maxlength="100">
                                <input type="hidden" id="productAddingBrandEditId" value="">
                                <button type="button" class="btn btn-outline-primary fw-semibold px-3" id="productAddingBrandSaveBtn" title="Save Brand">
                                    <i class="bi bi-plus-lg me-1"></i>Save
                                </button>
                                <button type="button" class="btn btn-outline-secondary d-none" id="productAddingBrandCancelEditBtn" title="Cancel Edit">
                                    Cancel
                                </button>
                            </div>
                            <div id="productAddingBrandFeedback" class="small mt-2 d-none"></div>
                            <div id="productAddingBrandList" class="mt-2 d-flex flex-wrap gap-2" style="max-height: 110px; overflow-y: auto;"></div>
                        </div>
                        <div id="productAddingLabelCountRows"></div>
                        <div id="productAddingLabelCountError" class="small text-danger mt-2" role="alert"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmProductAddingLabelCounts">
                            <i class="bi bi-printer-fill me-1"></i>Generate labels
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form action="<?= $formAction ?>" method="POST" enctype="multipart/form-data" id="formPurchaseBill" class="needs-validation <?= !empty($savedBill) ? 'd-none' : '' ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <?php if (str_contains($formAction, '/m/product_adding/store') || !empty($productAddingEdit)): ?>
            <input type="hidden" name="product_adding_redirect" value="1">
        <?php endif; ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
        <?php endif; ?>

        <!-- Section 1: Purchase & Supplier Details -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center">
                <i class="bi bi-receipt text-primary me-2 fs-6"></i>
                <h6 class="mb-0 fw-bold text-dark">Purchase & Supplier Details</h6>
            </div>
            <div class="card-body p-3">
                <div class="row g-2 mb-2">
                    <?php if (empty($selectedCid)): ?>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small fw-semibold mb-1">Target Concern <span class="text-danger">*</span></label>
                            <select name="concern_id" id="billConcernId" class="form-select form-select-sm" required>
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

                    <div class="<?= empty($selectedCid) ? 'col-md-2' : 'col-md-3' ?>">
                        <label class="form-label small fw-semibold mb-1">Invoice / Bill No <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_invoice_no" id="billSupplierInvoiceNo" class="form-control form-control-sm" placeholder="e.g. INV-2026-8891" value="<?= htmlspecialchars($isEdit ? ($bill['supplier_invoice_no'] ?? '') : '') ?>" required>
                        <div class="invalid-feedback" id="billSupplierInvoiceFeedback" aria-live="polite">Invoice / Bill number is required.</div>
                        <div class="form-text small" id="billSupplierInvoiceStatus" aria-live="polite"></div>
                    </div>

                    <div class="<?= empty($selectedCid) ? 'col-md-3' : 'col-md-4' ?>">
                        <label class="form-label small fw-semibold mb-1">Supplier <?= $supplierIsOptional ? '<span class="text-muted">(optional)</span>' : '<span class="text-danger">*</span>' ?></label>
                        <?php
                        $selectedSupplierLabel = '';
                        if ($isEdit && !empty($bill['supplier_id'])) {
                            foreach ($suppliers as $sup) {
                                if ((int) $sup['id'] === (int) $bill['supplier_id']) {
                                    $selectedSupplierLabel = $sup['name'] . (!empty($sup['company_name']) ? ' (' . $sup['company_name'] . ')' : '');
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="input-group input-group-sm flex-nowrap">
                            <select name="supplier_id" id="supplierSelect" class="d-none" <?= $supplierIsOptional ? '' : 'required' ?>>
                                <option value=""><?= $supplierIsOptional ? '-- No supplier --' : '-- Select registered supplier --' ?></option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= (int) $sup['id'] ?>" data-district="<?= htmlspecialchars($sup['district'] ?? '') ?>" <?= ($isEdit && $bill['supplier_id'] == $sup['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sup['name']) ?> <?= !empty($sup['company_name']) ? ('(' . htmlspecialchars($sup['company_name']) . ')') : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="position-relative flex-grow-1" style="min-width: 0;">
                                <input type="text"
                                       id="supplierSearchInput"
                                       class="form-control form-control-sm"
                                       placeholder="Type supplier name..."
                                       autocomplete="off"
                                       value="<?= htmlspecialchars($selectedSupplierLabel) ?>">
                                <div id="supplierAutocompleteMenu" class="dropdown-menu shadow-sm" style="min-width: 100%; width: max-content; max-width: min(440px, 90vw); max-height: 260px; overflow-y: auto; overflow-x: hidden; z-index: 1055;"></div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary text-nowrap px-2" id="viewSupplierBtn" title="View Suppliers">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <?php if (!empty($allowQuickSupplier)): ?>
                                <button type="button" class="btn btn-outline-primary text-nowrap px-2" id="openQuickSupplier" data-bs-toggle="modal" data-bs-target="#modalQuickSupplier">
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="invalid-feedback">Please choose a supplier.</div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Purchase Date <span class="text-danger">*</span></label>
                        <input type="date" name="bill_date" class="form-control form-control-sm" value="<?= htmlspecialchars($isEdit ? $bill['bill_date'] : date('Y-m-d')) ?>" required>
                        <div class="invalid-feedback">Valid purchase date is required.</div>
                    </div>

                    <div class="<?= empty($selectedCid) ? 'col-md-2' : 'col-md-3' ?>">
                        <label class="form-label small fw-semibold mb-1">Invoice Attachment <span class="text-danger">*</span></label>
                        <input type="file" name="attachments[]" id="billAttachments" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple <?= ($isEdit && !empty($existingAttachments)) ? '' : 'required' ?>>
                        <div class="invalid-feedback">Please choose at least one tax invoice / bill attachment file.</div>
                        <?php if (!empty($existingAttachments)): ?>
                            <div class="mt-1 small">
                                <?php foreach ($existingAttachments as $index => $attachmentPath): ?>
                                    <a href="<?= htmlspecialchars(\App\Services\CloudinaryService::url($attachmentPath)) ?>" target="_blank" rel="noopener" class="fw-semibold text-primary me-2">
                                        <i class="bi bi-paperclip"></i> Attachment <?= $index + 1 ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                $defaultGlobalTaxMode = 'Exclude GST';
                $defaultGlobalGstType = 'Exempted';
                $defaultGlobalTaxInputType = 'Percentage';
                if ($isEdit && !empty($existingItems)) {
                    $firstItem = $existingItems[0];
                    $defaultGlobalTaxMode = $firstItem['tax_mode'] ?? 'Exclude GST';
                    $defaultGlobalGstType = $firstItem['gst_type'] ?? 'Exempted';
                    $defaultGlobalTaxInputType = $firstItem['tax_input_type'] ?? 'Percentage';
                }
                ?>
                <!-- Row 2: Payment, Overall Tax Controls & Notes -->
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Payment Status</label>
                        <select name="payment_status" id="billPaymentStatus" class="form-select form-select-sm">
                            <option value="unpaid" <?= ($bill['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            <option value="partial" <?= ($bill['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>>Partial</option>
                            <option value="paid" <?= ($bill['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Payment Method</label>
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
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Tax Mode</label>
                        <select id="globalTaxMode" class="form-select form-select-sm" onchange="syncGlobalTaxControlsToAllRows()">
                            <option value="Exclude GST" <?= $defaultGlobalTaxMode === 'Exclude GST' ? 'selected' : '' ?>>Exclude GST</option>
                            <option value="Include GST" <?= $defaultGlobalTaxMode === 'Include GST' ? 'selected' : '' ?>>Include GST</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">GST Type</label>
                        <select id="globalGstType" class="form-select form-select-sm" onchange="syncGlobalTaxControlsToAllRows()">
                            <option value="Exempted" <?= $defaultGlobalGstType === 'Exempted' ? 'selected' : '' ?>>Exempted</option>
                            <option value="Both (CGST and SGST)" <?= $defaultGlobalGstType === 'Both (CGST and SGST)' ? 'selected' : '' ?>>Both (CGST & SGST)</option>
                            <option value="CGST" <?= $defaultGlobalGstType === 'CGST' ? 'selected' : '' ?>>CGST</option>
                            <option value="SGST" <?= $defaultGlobalGstType === 'SGST' ? 'selected' : '' ?>>SGST</option>
                            <option value="IGST" <?= $defaultGlobalGstType === 'IGST' ? 'selected' : '' ?>>IGST</option>
                        </select>
                    </div>
                    <div class="col-md-1" id="globalTaxInputTypeWrap" style="<?= $defaultGlobalGstType === 'Exempted' ? 'display:none;' : '' ?>">
                        <label class="form-label small fw-semibold mb-1">Mode</label>
                        <select id="globalTaxInputType" class="form-select form-select-sm" onchange="syncGlobalTaxControlsToAllRows()">
                            <option value="Percentage" <?= $defaultGlobalTaxInputType === 'Percentage' ? 'selected' : '' ?>>%</option>
                            <option value="Amount" <?= $defaultGlobalTaxInputType === 'Amount' ? 'selected' : '' ?>>₹</option>
                        </select>
                    </div>
                    <div class="col-md">
                        <label class="form-label small fw-semibold mb-1">Purchase Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Gate pass / vehicle / notes" value="<?= htmlspecialchars($isEdit ? ($bill['notes'] ?? '') : '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isProductAddingForm): ?>
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
                            </div>
                            <button type="button" class="btn btn-primary btn-sm px-3" onclick="addRollRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Row
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

                        <!-- Inline Roll Entry Table -->
                        <div style="overflow: visible;" class="mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0" id="rollsEntryTable" style="table-layout: fixed; width: 100%;">
                                <thead class="table-light small text-uppercase text-muted text-nowrap">
                                    <tr>
                                        <th class="text-center px-1" style="width: 3%;">#</th>
                                        <th class="px-1" style="width: 13%;">Fabric Name <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 10%;">Color <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 10%;">Pattern <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 7%;">Qty <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 9%;">Weight (kg) <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 9%;">Price (₹) <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 12%;">HSN</th>
                                        <th class="px-1" style="width: 13%;">GST</th>
                                        <th class="px-1 text-center" style="width: 10%;">Photo</th>
                                        <th class="text-center px-1" style="width: 4%;">Del</th>
                                    </tr>
                                </thead>
                                <tbody id="rollsContainer"></tbody>
                            </table>
                        </div>

                        <div id="noRollsPlaceholder" class="p-4 bg-light rounded-3 text-center text-muted mb-3">
                            <i class="bi bi-layers fs-3 d-block mb-1 text-secondary"></i>
                            No fabric rolls added. Click <strong>+ Add Fabric Roll</strong> to add roll items.
                        </div>

                        <!-- Fabric Rolls Inventory & Details Table (Hidden to keep single-table entry) -->
                        <div class="card border mb-3 shadow-none rounded-3 d-none">
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
                            <button type="button" class="btn btn-primary btn-sm px-3" onclick="addOtherRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Row
                            </button>
                        </div>

                        <!-- Inline Supply Entry Table -->
                        <div style="overflow: visible;" class="mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0" id="otherEntryTable" style="table-layout: fixed; width: 100%;">
                                <thead class="table-light small text-uppercase text-muted text-nowrap">
                                    <tr>
                                        <th class="text-center px-1" style="width: 4%;">#</th>
                                        <th class="px-1" style="width: 25%;">Item Name <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 9%;">Qty <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 14%;">Unit <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 12%;">Price (₹) <span class="text-danger">*</span></th>
                                        <th class="px-1" style="width: 14%;">HSN</th>
                                        <th class="px-1" style="width: 18%;">GST</th>
                                        <th class="text-center px-1" style="width: 4%;">Del</th>
                                    </tr>
                                </thead>
                                <tbody id="garmentOtherContainer"></tbody>
                            </table>
                        </div>

                        <div id="noSuppliesPlaceholder" class="p-4 bg-light rounded-3 text-center text-muted mb-3">
                            <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                            No general supplies added. Click <strong>+ Add Supply</strong> to add buttons, threads, zippers or trims.
                        </div>

                        <!-- Other Garment Supplies Details Table (Hidden to keep single-table entry) -->
                        <div class="card border mb-3 shadow-none rounded-3 d-none">
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
                                        <tr><td colspan="10" class="text-center py-3 text-muted">Add garment supplies to see their purchase details.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 3. FINISHED GOODS (BASED ON REFERENCE IMAGE 3) -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="paneFinished" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3 <?= $isProductAddingForm ? 'd-none' : '' ?>">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark" style="color: #6d28d9;"><i class="bi bi-tag text-purple me-1"></i> Finished Goods / Apparel Items</h6>
                                <small class="text-muted">Record complete garments, t-shirts, shirts or series packs received into inventory.</small>
                            </div>
                            <button type="button" class="btn btn-sm text-white px-3 fw-semibold shadow-sm" style="background: #8b5cf6;" onclick="addFinishedRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Finished Good
                            </button>
                        </div>

                        <!-- Dynamic Finished Goods Input Cards -->
                        <div id="finishedGoodsContainer"></div>

                        <div id="noFinishedPlaceholder" class="p-4 bg-light rounded-3 text-center text-muted mb-3 <?= $isProductAddingForm ? 'd-none' : '' ?>">
                            <i class="bi bi-tag fs-3 d-block mb-1 text-secondary"></i>
                            No finished goods added. Click <strong>+ Add Finished Good</strong> to add ready apparel.
                        </div>

                        <!-- Finished Goods Inventory & Details Table (Hidden to keep single-table entry) -->
                        <div class="card border mb-3 shadow-none rounded-3 d-none">
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
        <?php else: ?>
        <!-- Section 2 (Purchase Billing Simplified): Single Inline Entry Table -->
        <style>
            #simplePurchaseEntryTable > :not(caption) > * > * {
                padding: 0.28rem 0.35rem !important;
                vertical-align: middle;
            }
            #simplePurchaseEntryTable .form-control-sm,
            #simplePurchaseEntryTable .form-select-sm {
                padding: 0.2rem 0.45rem;
                font-size: 0.82rem;
                height: 28px;
                min-height: 28px;
            }
            #simplePurchaseEntryTable .input-group-sm > .btn {
                padding: 0.15rem 0.45rem;
                height: 28px;
            }
        </style>
        <div class="card border-0 shadow-sm rounded-3 mb-3" id="simplePurchaseBillingSection">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-table text-primary fs-6"></i>
                    <h6 class="mb-0 fw-bold text-dark">Purchase Billing Items</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border" id="simpleItemsCountBadge">0 Items</span>
                    <button type="button" class="btn btn-primary btn-sm px-3" onclick="addSimplePurchaseRow()">
                        <i class="bi bi-plus-circle me-1"></i> Add Row
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div style="overflow: visible;">
                    <table class="table table-sm table-bordered align-middle mb-0" id="simplePurchaseEntryTable" style="table-layout: fixed; width: 100%;">
                        <thead class="table-light small text-uppercase text-muted text-nowrap">
                            <tr>
                                <th class="text-center px-1" style="width: 3%;">#</th>
                                <th class="px-1" style="width: 14%;">Type <span class="text-danger">*</span></th>
                                <th class="px-1" style="width: 20%;">Name <span class="text-danger">*</span></th>
                                <th class="px-1" style="width: 13%;">HSN</th>
                                <th class="px-1" style="width: 8%;">Qty <span class="text-danger">*</span></th>
                                <th class="px-1" style="width: 10%;">Price <span class="text-danger">*</span></th>
                                <th class="text-center px-1" style="width: 14%;">GST (%)</th>
                                <th class="text-end px-1 bg-success bg-opacity-10 text-success" style="width: 14%;">Total</th>
                                <th class="text-center px-1" style="width: 4%;">Del</th>
                            </tr>
                        </thead>
                        <tbody id="simplePurchaseItemsContainer"></tbody>
                    </table>
                </div>
                <div id="noSimpleItemsPlaceholder" class="text-center py-4 text-muted bg-light border-top" style="display: none;">
                    No items added yet. Click <strong>"+ Add Row"</strong> to begin.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Section 3: Summary & Total Price Card (Exact Image 2 Bottom Layout) -->
        <div class="card border-0 shadow-sm rounded-3 mb-4 p-3 bg-white">
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
                        <a href="<?= $backUrl ?>" class="btn btn-light border px-4 py-2 fw-semibold text-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn text-white px-4 py-2 fw-semibold shadow-sm rounded-2 d-flex align-items-center gap-2" style="background: #4f46e5;" id="btnSavePurchase">
                            <i class="bi bi-check-circle fs-6"></i> <?= htmlspecialchars($submitLabel) ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($allowQuickSupplier)): ?>
<div class="modal fade" id="modalQuickSupplier" tabindex="-1" aria-labelledby="modalQuickSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <form id="quickSupplierForm" class="modal-content needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
            <input type="hidden" name="supplier_id" id="quickSupplierId" value="">
            <?php if (!empty($selectedCid)): ?>
                <input type="hidden" name="concern_id" value="<?= (int) $selectedCid ?>">
            <?php else: ?>
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalQuickSupplierLabel">Add Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Concern <span class="text-danger">*</span></label>
                        <select name="concern_id" class="form-select" required>
                            <option value="">-- Choose Target Concern --</option>
                            <?php foreach ($allConcerns ?? [] as $con): ?>
                                <option value="<?= (int) $con['id'] ?>"><?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
            <?php endif; ?>
            <?php if (!empty($selectedCid)): ?>
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalQuickSupplierLabel">Add Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
            <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact Person / Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required minlength="2" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Company / Mill Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" required minlength="2" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" required pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">GSTIN</label>
                            <input type="text" name="gstin" class="form-control text-uppercase font-monospace" maxlength="15" pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}[Zz][0-9A-Za-z]{1}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">District <span class="text-danger">*</span></label>
                            <input type="text" name="district" class="form-control" required minlength="2">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control" rows="2" required minlength="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">State <span class="text-danger">*</span></label>
                            <input type="text" name="state" class="form-control" required minlength="2" placeholder="Enter state">
                        </div>
                    </div>
                    <div class="alert alert-danger mt-3 mb-0 d-none" id="quickSupplierError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveQuickSupplier">
                        <i class="bi bi-check-circle me-1"></i> Save &amp; Select Supplier
                    </button>
                </div>
        </form>
    </div>
</div>
<script>
(function () {
    const form = document.getElementById('quickSupplierForm');
    const supplierSelect = document.getElementById('supplierSelect');
    const saveButton = document.getElementById('saveQuickSupplier');
    const errorBox = document.getElementById('quickSupplierError');
    const idInput = document.getElementById('quickSupplierId');
    const titleEls = document.querySelectorAll('#modalQuickSupplier #modalQuickSupplierLabel');
    const openBtn = document.getElementById('openQuickSupplier');
    if (!form || !supplierSelect || !saveButton || !errorBox) return;

    const resetQuickSupplierModalMode = function () {
        form.reset();
        if (idInput) idInput.value = '';
        titleEls.forEach(function (el) { el.textContent = 'Add Supplier'; });
        saveButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save & Select Supplier';
        errorBox.classList.add('d-none');
    };

    if (openBtn) {
        openBtn.addEventListener('click', resetQuickSupplierModalMode);
    }

    window.openQuickSupplierEdit = function (sup) {
        if (!sup || !sup.id) return;
        resetQuickSupplierModalMode();
        if (idInput) idInput.value = String(sup.id);
        titleEls.forEach(function (el) { el.textContent = 'Edit Supplier'; });
        saveButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> Update Supplier';
        ['name', 'company_name', 'phone', 'gstin', 'email', 'district', 'address', 'state'].forEach(function (field) {
            const el = form.querySelector('[name="' + field + '"]');
            if (el) el.value = sup[field] || '';
        });
        const concernEl = form.querySelector('select[name="concern_id"]');
        if (concernEl && sup.concern_id) concernEl.value = String(sup.concern_id);
        const viewModalEl = document.getElementById('modalViewSupplier');
        if (viewModalEl) bootstrap.Modal.getOrCreateInstance(viewModalEl).hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQuickSupplier')).show();
    };

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;

        const isEditSup = Boolean(idInput && idInput.value.trim() !== '');
        const targetUrl = isEditSup ? <?= json_encode($quickSupplierUpdateUrl) ?> : <?= json_encode($quickSupplierSaveUrl) ?>;
        if (!targetUrl) {
            errorBox.textContent = 'Supplier endpoint is not configured.';
            errorBox.classList.remove('d-none');
            return;
        }

        saveButton.disabled = true;
        errorBox.classList.add('d-none');
        try {
            const response = await fetch(targetUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            });
            const result = await response.json();
            if (!response.ok || !result.success || !result.supplier) {
                throw new Error(result.message || 'Unable to save supplier.');
            }

            const supplier = result.supplier;
            const label = supplier.name + (supplier.company_name ? ' (' + supplier.company_name + ')' : '');
            const existingOpt = Array.from(supplierSelect.options).find(function (opt) { return String(opt.value) === String(supplier.id); });
            if (existingOpt) {
                existingOpt.text = label;
                existingOpt.dataset.district = supplier.district || '';
                existingOpt.selected = true;
            } else {
                const option = new Option(label, supplier.id, true, true);
                option.dataset.district = supplier.district || '';
                supplierSelect.add(option);
            }
            if (window.registerPurchaseSupplier) {
                window.registerPurchaseSupplier(supplier);
            }
            supplierSelect.dispatchEvent(new Event('change', { bubbles: true }));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQuickSupplier')).hide();
            resetQuickSupplierModalMode();
        } catch (error) {
            errorBox.textContent = error.message || 'Unable to save supplier.';
            errorBox.classList.remove('d-none');
        } finally {
            saveButton.disabled = false;
        }
    });
    if (typeof refreshProductAddingAttributeSelectors === 'function') {
        refreshProductAddingAttributeSelectors();
    }
})();
</script>
<?php endif; ?>

<!-- View Supplier Details / Directory Modal -->
<div class="modal fade" id="modalViewSupplier" tabindex="-1" aria-labelledby="modalViewSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold text-dark" id="modalViewSupplierLabel">
                    <i class="bi bi-person-vcard text-primary me-2"></i>Supplier Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="viewSupDetailsPane" style="display: none;">
                    <div class="row g-3 small">
                        <div class="col-6">
                            <div class="text-muted">Supplier Code</div>
                            <div class="fw-semibold font-monospace" id="viewSupCode">-</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">Contact Name</div>
                            <div class="fw-semibold" id="viewSupName">-</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">Company / Mill Name</div>
                            <div class="fw-semibold" id="viewSupCompany">-</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">Phone</div>
                            <div class="fw-semibold" id="viewSupPhone">-</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">Email</div>
                            <div class="fw-semibold" id="viewSupEmail">-</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">GSTIN</div>
                            <div class="fw-semibold font-monospace" id="viewSupGstin">-</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">District</div>
                            <div class="fw-semibold" id="viewSupDistrict">-</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">State</div>
                            <div class="fw-semibold" id="viewSupState">-</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted">Address</div>
                            <div class="fw-semibold" id="viewSupAddress">-</div>
                        </div>
                    </div>
                </div>
                <div id="viewSupListPane" style="display: none;">
                    <div class="mb-2">
                        <input type="text" id="viewSupDirectoryFilter" class="form-control form-control-sm" placeholder="Filter suppliers by name, company, phone, or district...">
                    </div>
                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                        <table class="table table-sm table-hover table-bordered align-middle mb-0 small">
                            <thead class="table-light text-nowrap">
                                <tr>
                                    <th>Code</th>
                                    <th>Contact Name</th>
                                    <th>Company / Mill</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>GSTIN</th>
                                    <th>Address</th>
                                    <th>District</th>
                                    <th>State</th>
                                    <th class="text-center" style="min-width: 95px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="viewSupDirectoryTbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <button type="button" class="btn btn-outline-primary btn-sm" id="viewSupBrowseAllBtn" style="display: none;">
                    <i class="bi bi-list-ul me-1"></i> Browse All Suppliers
                </button>
                <button type="button" class="btn btn-secondary btn-sm ms-auto" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const suppliersCatalog = <?= json_encode(array_values($suppliers ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const supplierSelect = document.getElementById('supplierSelect');
    const searchInput = document.getElementById('supplierSearchInput');
    const menu = document.getElementById('supplierAutocompleteMenu');
    const viewBtn = document.getElementById('viewSupplierBtn');
    if (!supplierSelect || !searchInput || !menu) return;

    const formatSupplierLabel = function (sup) {
        return (sup.name || '') + (sup.company_name ? ' (' + sup.company_name + ')' : '');
    };

    const findSupplierById = function (id) {
        const targetId = String(id || '');
        return suppliersCatalog.find(function (s) { return String(s.id) === targetId; }) || null;
    };

    const syncViewButton = function () {
        // Keep View button always clickable: shows selected supplier details or full supplier directory.
        if (viewBtn) viewBtn.disabled = false;
    };

    const selectSupplier = function (sup) {
        supplierSelect.value = String(sup.id);
        searchInput.value = formatSupplierLabel(sup);
        searchInput.setCustomValidity('');
        menu.classList.remove('show');
        syncViewButton();
        supplierSelect.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const renderSuggestions = function (query) {
        const q = String(query || '').trim().toLowerCase();
        const matches = suppliersCatalog.filter(function (sup) {
            if (!q) return true;
            const hay = [sup.name, sup.company_name, sup.phone, sup.code, sup.district]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            return hay.includes(q);
        }).slice(0, 30);

        menu.innerHTML = '';
        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'dropdown-item-text text-muted small py-2';
            empty.textContent = 'No matching suppliers found.';
            menu.appendChild(empty);
        } else {
            matches.forEach(function (sup) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'dropdown-item d-flex flex-column align-items-start py-2';
                item.style.whiteSpace = 'normal';
                const left = document.createElement('span');
                left.className = 'fw-semibold text-dark';
                left.textContent = formatSupplierLabel(sup);
                const right = document.createElement('small');
                right.className = 'text-muted';
                right.textContent = [sup.phone, sup.district].filter(Boolean).join(' • ');
                item.appendChild(left);
                item.appendChild(right);
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectSupplier(sup);
                });
                menu.appendChild(item);
            });
        }
        menu.classList.add('show');
    };

    searchInput.addEventListener('focus', function () {
        renderSuggestions(searchInput.value);
    });

    searchInput.addEventListener('input', function () {
        const current = findSupplierById(supplierSelect.value);
        if (!current || formatSupplierLabel(current) !== searchInput.value) {
            supplierSelect.value = '';
            syncViewButton();
        }
        renderSuggestions(searchInput.value);
    });

    searchInput.addEventListener('blur', function () {
        setTimeout(function () { menu.classList.remove('show'); }, 150);
    });

    supplierSelect.addEventListener('change', syncViewButton);

    window.registerPurchaseSupplier = function (sup) {
        if (!sup || !sup.id) return;
        const idx = suppliersCatalog.findIndex(function (s) { return String(s.id) === String(sup.id); });
        if (idx === -1) {
            suppliersCatalog.push(sup);
        } else {
            suppliersCatalog[idx] = Object.assign({}, suppliersCatalog[idx], sup);
        }
        selectSupplier(sup);
    };

    const deleteSupplierById = async function (sup) {
        const deleteUrl = <?= json_encode($quickSupplierDeleteUrl) ?>;
        if (!deleteUrl || !sup || !sup.id) return;
        if (!confirm('Delete supplier "' + (sup.name || sup.company_name || sup.code) + '"?')) return;

        const fd = new FormData();
        fd.append('csrf_token', <?= json_encode($csrfToken ?? '') ?>);
        fd.append('supplier_id', String(sup.id));
        try {
            const resp = await fetch(deleteUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            });
            const result = await resp.json();
            if (!resp.ok || !result.success) {
                throw new Error(result.message || 'Unable to delete supplier.');
            }
            const idx = suppliersCatalog.findIndex(function (s) { return String(s.id) === String(sup.id); });
            if (idx !== -1) suppliersCatalog.splice(idx, 1);

            const opt = Array.from(supplierSelect.options).find(function (o) { return String(o.value) === String(sup.id); });
            if (opt) opt.remove();

            if (String(supplierSelect.value) === String(sup.id)) {
                supplierSelect.value = '';
                searchInput.value = '';
                supplierSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            renderSupplierDirectory(document.getElementById('viewSupDirectoryFilter')?.value || '');
        } catch (err) {
            alert(err.message || 'Unable to delete supplier.');
        }
    };

    const renderSupplierDirectory = function (filterText = '') {
        const tbody = document.getElementById('viewSupDirectoryTbody');
        if (!tbody) return;
        const q = String(filterText || '').trim().toLowerCase();
        const rows = suppliersCatalog.filter(function (sup) {
            if (!q) return true;
            const hay = [sup.code, sup.name, sup.company_name, sup.phone, sup.email, sup.gstin, sup.address, sup.district, sup.state]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            return hay.includes(q);
        });
        tbody.innerHTML = '';
        if (rows.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 10;
            td.className = 'text-center text-muted py-3';
            td.textContent = 'No suppliers found.';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }
        rows.forEach(function (sup) {
            const tr = document.createElement('tr');
            const addCell = function (val, cls = '') {
                const td = document.createElement('td');
                if (cls) td.className = cls;
                td.textContent = val || '-';
                return td;
            };

            const tdAction = document.createElement('td');
            tdAction.className = 'text-center text-nowrap';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-outline-secondary btn-sm py-0 px-2 me-1';
            editBtn.title = 'Edit Supplier';
            editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
            editBtn.addEventListener('click', function () {
                if (window.openQuickSupplierEdit) window.openQuickSupplierEdit(sup);
            });

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn btn-outline-danger btn-sm py-0 px-2';
            delBtn.title = 'Delete Supplier';
            delBtn.innerHTML = '<i class="bi bi-trash3"></i>';
            delBtn.addEventListener('click', function () {
                deleteSupplierById(sup);
            });

            tdAction.append(editBtn, delBtn);
            tr.append(
                addCell(sup.code, 'font-monospace text-nowrap'),
                addCell(sup.name, 'fw-semibold'),
                addCell(sup.company_name),
                addCell(sup.phone, 'text-nowrap'),
                addCell(sup.email),
                addCell(sup.gstin, 'font-monospace text-nowrap'),
                addCell(sup.address),
                addCell(sup.district),
                addCell(sup.state),
                tdAction
            );
            tbody.appendChild(tr);
        });
    };

    const showSupplierDirectoryPane = function () {
        const detailsPane = document.getElementById('viewSupDetailsPane');
        const listPane = document.getElementById('viewSupListPane');
        const browseBtn = document.getElementById('viewSupBrowseAllBtn');
        const titleEl = document.getElementById('modalViewSupplierLabel');
        const filterEl = document.getElementById('viewSupDirectoryFilter');
        if (detailsPane) detailsPane.style.display = 'none';
        if (listPane) listPane.style.display = 'block';
        if (browseBtn) browseBtn.style.display = 'none';
        if (titleEl) titleEl.innerHTML = '<i class="bi bi-people text-primary me-2"></i>Registered Suppliers';
        if (filterEl) filterEl.value = '';
        renderSupplierDirectory('');
    };

    const dirFilter = document.getElementById('viewSupDirectoryFilter');
    if (dirFilter) {
        dirFilter.addEventListener('input', function () {
            renderSupplierDirectory(dirFilter.value);
        });
    }

    const browseAllBtn = document.getElementById('viewSupBrowseAllBtn');
    if (browseAllBtn) {
        browseAllBtn.addEventListener('click', showSupplierDirectoryPane);
    }

    if (viewBtn) {
        viewBtn.addEventListener('click', function () {
            const sup = findSupplierById(supplierSelect.value);
            if (sup) {
                const detailsPane = document.getElementById('viewSupDetailsPane');
                const listPane = document.getElementById('viewSupListPane');
                const browseBtn = document.getElementById('viewSupBrowseAllBtn');
                const titleEl = document.getElementById('modalViewSupplierLabel');
                if (detailsPane) detailsPane.style.display = 'block';
                if (listPane) listPane.style.display = 'none';
                if (browseBtn) browseBtn.style.display = 'inline-block';
                if (titleEl) titleEl.innerHTML = '<i class="bi bi-person-vcard text-primary me-2"></i>Supplier Details';
                document.getElementById('viewSupCode').textContent = sup.code || '-';
                document.getElementById('viewSupName').textContent = sup.name || '-';
                document.getElementById('viewSupCompany').textContent = sup.company_name || '-';
                document.getElementById('viewSupPhone').textContent = sup.phone || '-';
                document.getElementById('viewSupEmail').textContent = sup.email || '-';
                document.getElementById('viewSupGstin').textContent = sup.gstin || '-';
                document.getElementById('viewSupDistrict').textContent = sup.district || '-';
                document.getElementById('viewSupState').textContent = sup.state || '-';
                document.getElementById('viewSupAddress').textContent = sup.address || '-';
            } else {
                showSupplierDirectoryPane();
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalViewSupplier')).show();
        });
    }

    syncViewButton();
})();
</script>

<!-- Add HSN Master Code Modal (Unified with Catalog Hierarchy) -->
<div class="modal fade" id="modalQuickHsn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-upc-scan text-primary me-1.5"></i> Add HSN Master Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="quickHsnInlineError" class="alert alert-danger d-none py-2 px-3 mb-3 small" role="alert" aria-live="polite"></div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">HSN Code <span class="text-danger">*</span></label>
                        <input type="text" id="newHsnCode" class="form-control font-monospace fw-bold" placeholder="4, 6, or 8 digits" maxlength="8" minlength="4" inputmode="numeric" pattern="[0-9]{4}|[0-9]{6}|[0-9]{8}" title="Enter a valid 4-, 6-, or 8-digit numeric HSN code" data-hsn-autocomplete data-hsn-type="HSN" data-hsn-description-target="#newHsnDesc" data-hsn-gst-target="#newHsnGstRate" autocomplete="off" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Category</label>
                        <input type="text" id="newHsnCategory" class="form-control" placeholder="e.g. Apparel, Fabric, Packaging" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">GST Rate (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" id="newHsnGstRate" class="form-control font-monospace" placeholder="0.00 to 100.00" required title="Enter a GST percentage from 0.00 to 100.00">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea id="newHsnDesc" class="form-control" rows="2" placeholder="e.g. T-shirts, singlets and other vests, knitted or crocheted" maxlength="255"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm px-3" onclick="saveAndSelectHsn()">
                    <i class="bi bi-check-circle me-1"></i> Save HSN Code
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
    <div class="modal-dialog modal-dialog-centered modal-xl">
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

<!-- Roll Photo Preview Modal -->
<div class="modal fade" id="modalRollPhotoPreview" tabindex="-1" aria-labelledby="modalRollPhotoPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-3">
            <div class="modal-header py-2 px-3 border-bottom">
                <h6 class="modal-title fw-bold text-truncate mb-0" id="modalRollPhotoPreviewTitle">Roll Photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 text-center bg-light">
                <img id="modalRollPhotoPreviewImg" src="" alt="Roll Photo Preview" class="img-fluid rounded border shadow-sm" style="max-height: 70vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<!-- Quick Add Attribute Values Modal -->
<div class="modal fade" id="quickAddAttributeValueModal" tabindex="-1" aria-labelledby="quickAddAttributeValueTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div>
                    <h5 class="modal-title fw-bold fs-6 mb-0" id="quickAddAttributeValueTitle">
                        <i class="bi bi-plus-circle text-primary me-2"></i>Quick Add Attribute Values
                    </h5>
                    <div class="small text-muted mt-1" id="quickAddAttrModalSubtitle">Select attributes and quickly add one or multiple values.</div>
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" id="quickAddAttrSummaryBadge">0 attributes</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-4">
                <div id="quickAddAttrRowsContainer" class="d-flex flex-column gap-3"></div>
                <div class="form-check mt-3 pt-2 border-top">
                    <input class="form-check-input" type="checkbox" id="quickAddAttrAutoSelect" checked>
                    <label class="form-check-label small fw-medium text-secondary" for="quickAddAttrAutoSelect">
                        Automatically select newly added values in this garment card
                    </label>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted" id="quickAddAttrFooterStatus">0 attributes selected</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnSaveQuickAttributeValues" onclick="submitQuickAddAttributeValues()">
                        <i class="bi bi-check2-circle me-1"></i>Save &amp; Add Values
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add New Attribute Modal -->
<div class="modal fade" id="addProductAttributeModal" tabindex="-1" aria-labelledby="addProductAttributeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold fs-6 mb-0" id="addProductAttributeModalTitle">
                    <i class="bi bi-tags text-primary me-2"></i>Add New Attribute
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="addAttrModalError" class="alert alert-danger py-2 px-3 small d-none mb-3"></div>
                <div class="mb-3">
                    <label for="addAttrModalName" class="form-label small fw-semibold">Attribute Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="addAttrModalName" placeholder="e.g. Fabric Type, Pattern, Sleeve" maxlength="50"
                        onkeydown="if(event.key==='Enter'){ event.preventDefault(); document.getElementById('addAttrModalValues')?.focus(); }">
                </div>
                <div>
                    <label for="addAttrModalValues" class="form-label small fw-semibold">Initial Value(s) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="addAttrModalValues" placeholder="e.g. Cotton, Linen (comma-separated)"
                        onkeydown="if(event.key==='Enter'){ event.preventDefault(); submitAddProductAttributeModal(); }">
                    <div class="form-text small">You can enter one value or multiple comma-separated values.</div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnCreateProductAttribute" onclick="submitAddProductAttributeModal()">
                    <i class="bi bi-plus-lg me-1"></i>Add Attribute
                </button>
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
const isProductAddingForm = <?= $isProductAddingForm ? 'true' : 'false' ?>;
const productAddingCategories = <?= json_encode($categories ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const productAddingSubcategories = <?= json_encode($subcategories ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const productAddingSubSubcategories = <?= json_encode($subSubcategories ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const productAddingAttributes = <?= json_encode($productAttributes ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const quickCategorySaveUrl = <?= json_encode($quickCategorySaveUrl) ?>;
const quickAttributeSaveUrl = <?= json_encode($quickAttributeSaveUrl) ?>;
const invoiceCheckUrl = <?= json_encode($invoiceCheckUrl) ?>;

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
    clearInlineHsnError();
    document.getElementById('newHsnCode').value = '';
    document.getElementById('newHsnDesc').value = '';
    document.getElementById('newHsnGstRate').value = '';
    const catEl = document.getElementById('newHsnCategory');
    if (catEl) catEl.value = '';
    const modal = new bootstrap.Modal(document.getElementById('modalQuickHsn'));
    modal.show();
}

function promptAddVariantHsn(cardId, rowIndex) {
    currentHsnTarget = { type: 'variant', id: cardId, rowIndex };
    clearInlineHsnError();
    document.getElementById('newHsnCode').value = '';
    document.getElementById('newHsnDesc').value = '';
    document.getElementById('newHsnGstRate').value = '';
    const catEl = document.getElementById('newHsnCategory');
    if (catEl) catEl.value = '';
    new bootstrap.Modal(document.getElementById('modalQuickHsn')).show();
}

function showInlineHsnError(message) {
    const error = document.getElementById('quickHsnInlineError');
    if (!error) return;
    error.textContent = message;
    error.classList.remove('d-none');
}

function clearInlineHsnError() {
    const error = document.getElementById('quickHsnInlineError');
    if (!error) return;
    error.textContent = '';
    error.classList.add('d-none');
}

function saveAndSelectHsn() {
    const code = document.getElementById('newHsnCode').value.trim().toUpperCase();
    const desc = document.getElementById('newHsnDesc').value.trim();
    const rateInput = document.getElementById('newHsnGstRate').value.trim();
    const rate = rateInput !== '' ? parseFloat(rateInput) : null;
    const category = document.getElementById('newHsnCategory')?.value.trim() || 'Garment and textile goods';

    if (!/^[0-9]{4}$|^[0-9]{6}$|^[0-9]{8}$/.test(code)) {
        showInlineHsnError('Enter a valid numeric HSN code with 4, 6, or 8 digits.');
        document.getElementById('newHsnCode').focus();
        return;
    }
    if (rateInput !== '' && (!Number.isFinite(rate) || rate < 0 || rate > 100)) {
        showInlineHsnError('Enter a GST percentage between 0.00 and 100.00.');
        document.getElementById('newHsnGstRate').focus();
        return;
    }

    const existing = createdHsnList.find(item => item.hsn_code === code && item.code_type === 'HSN');
    if (existing) {
        clearInlineHsnError();
        applyCreatedHsn(existing);
        return;
    }

    const formData = new FormData();
    formData.append('hsn_code', code);
    formData.append('code_type', 'HSN');
    formData.append('description', desc);
    formData.append('category', category);
    formData.append('gst_rate', rate === null ? '' : rate);
    formData.append('rate_note', '');
    if (isProductAddingForm) {
        formData.append('concern_id', String(getProductAddingConcernId() || ''));
    }

    fetch('<?= $hsnSaveUrl ?>', {
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
    } else if (currentHsnTarget.type === 'variant') {
        const row = document.querySelector(`#finishedCard_${currentHsnTarget.id} #fgVariantRow_${currentHsnTarget.id}_${currentHsnTarget.rowIndex}`);
        const activeInput = row?.querySelector('.fg-variant-hsn');
        if (activeInput) setHsnInputValue(activeInput, selectedHsn);
        onFinishedVariantHsnChange(currentHsnTarget.id, currentHsnTarget.rowIndex);
    } else if (currentHsnTarget.type === 'simple') {
        const activeInput = document.querySelector(`#simpleCard_${currentHsnTarget.id} .simple-input-hsn`);
        if (activeInput) setHsnInputValue(activeInput, selectedHsn);
        onSimpleHsnChange(currentHsnTarget.id);
        syncSimplePurchaseTable();
    }

    const modalEl = document.getElementById('modalQuickHsn');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

function renderRollPhotoPreviewChip(rollId, photoUrl, photoName = 'Roll Photo') {
    const previewContainer = document.getElementById(`photoPreview_${rollId}`);
    const triggerEl = document.getElementById(`rollPhotoTrigger_${rollId}`);
    if (!previewContainer) return;

    const safeUrl = escapeHtml(photoUrl);
    const safeName = escapeHtml(photoName || 'Roll Photo');
    previewContainer.innerHTML = `
        <div class="roll-photo-preview-chip" title="${safeName}">
            <a href="${safeUrl}" target="_blank" rel="noopener" class="roll-photo-thumb-link" title="View full image: ${safeName}" onclick="return openRollPhotoModal(event, ${rollId})">
                <img src="${safeUrl}" alt="${safeName}">
            </a>
            <label for="rollPhotoInput_${rollId}" class="roll-photo-action-btn roll-photo-change-btn" title="Change photo (${safeName})">
                <i class="bi bi-pencil"></i>
            </label>
            <button type="button" class="roll-photo-action-btn roll-photo-clear-btn" onclick="clearRollPhoto(${rollId})" title="Remove photo">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    `;
    previewContainer.classList.remove('d-none');
    if (triggerEl) triggerEl.classList.add('d-none');
}

function resetRollPhotoPreview(rollId) {
    const previewContainer = document.getElementById(`photoPreview_${rollId}`);
    const triggerEl = document.getElementById(`rollPhotoTrigger_${rollId}`);
    if (previewContainer) {
        previewContainer.innerHTML = '';
        previewContainer.classList.add('d-none');
    }
    if (triggerEl) triggerEl.classList.remove('d-none');
}

function openRollPhotoModal(event, rollId) {
    const card = document.getElementById(`rollCard_${rollId}`);
    const photoUrl = card?.dataset?.photoUrl || '';
    const photoName = card?.dataset?.photoName || 'Roll Photo';
    const modalEl = document.getElementById('modalRollPhotoPreview');
    const modalImg = document.getElementById('modalRollPhotoPreviewImg');
    const modalTitle = document.getElementById('modalRollPhotoPreviewTitle');
    if (photoUrl && modalEl && modalImg && window.bootstrap?.Modal) {
        if (event) event.preventDefault();
        modalImg.src = photoUrl;
        if (modalTitle) modalTitle.textContent = photoName;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        return false;
    }
    return true;
}

function previewRollPhoto(input, rollId) {
    const card = document.getElementById(`rollCard_${rollId}`);
    if (!card) return;

    const file = input.files && input.files[0];

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const dataUrl = e.target.result;
            card.dataset.photoUrl = dataUrl;
            card.dataset.photoName = file.name;
            renderRollPhotoPreviewChip(rollId, dataUrl, file.name);
            syncRollsTable();
        };
        reader.readAsDataURL(file);
    } else {
        delete card.dataset.photoUrl;
        delete card.dataset.photoName;
        resetRollPhotoPreview(rollId);
        syncRollsTable();
    }
}

function clearRollPhoto(rollId) {
    const card = document.getElementById(`rollCard_${rollId}`);
    if (!card) return;
    const fileInput = card.querySelector('.roll-input-photo');
    if (fileInput) fileInput.value = '';
    const existingPhotoInput = card.querySelector('input[name$="[existing_photo]"]');
    if (existingPhotoInput) existingPhotoInput.value = '';
    delete card.dataset.photoUrl;
    delete card.dataset.photoName;
    resetRollPhotoPreview(rollId);
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

    const exemptBadge = document.getElementById('rollExemptBadge_' + rollId);
    if (gstType === 'Exempted') {
        // When Exempted: dynamic fields do NOT come
        if (dynamicContainer) dynamicContainer.classList.add('d-none');
        if (exemptBadge) exemptBadge.classList.remove('d-none');
    } else {
        // Show fields based on Image 2
        if (dynamicContainer) dynamicContainer.classList.remove('d-none');
        if (exemptBadge) exemptBadge.classList.add('d-none');

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

    const exemptBadge = document.getElementById('otherExemptBadge_' + otherId);
    if (gstType === 'Exempted') {
        if (dynamicContainer) dynamicContainer.classList.add('d-none');
        if (exemptBadge) exemptBadge.classList.remove('d-none');
    } else {
        if (dynamicContainer) dynamicContainer.classList.remove('d-none');
        if (exemptBadge) exemptBadge.classList.add('d-none');

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

    const exemptBadge = document.getElementById('otherExemptBadge_' + otherId);
    if (gstType === 'Exempted') {
        if (dynamicContainer) dynamicContainer.classList.add('d-none');
        if (exemptBadge) exemptBadge.classList.remove('d-none');
    } else {
        if (dynamicContainer) dynamicContainer.classList.remove('d-none');
        if (exemptBadge) exemptBadge.classList.add('d-none');

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
    const catalogGstRate = card.querySelector('.fg-input-catalog-gst-rate');
    const hsnRate = parseFloat(hsnInput?.dataset.hsnGstRate || '');
    if (catalogGstRate && Number.isFinite(hsnRate)) {
        catalogGstRate.value = hsnRate.toFixed(2);
    }
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

function getProductAddingConcernId() {
    const concernSelect = document.getElementById('billConcernId');
    return parseInt(concernSelect?.value || <?= (int) ($selectedCid ?? 0) ?>, 10) || 0;
}

function getProductAddingScopedRows(rows) {
    const concernId = getProductAddingConcernId();
    return (Array.isArray(rows) ? rows : []).filter(row => {
        const rowConcernId = parseInt(row.concern_id, 10) || 0;
        return !concernId || !rowConcernId || rowConcernId === 1 || rowConcernId === concernId;
    });
}

function getProductAddingAttributeValues(type, categoryId = '', subcategoryId = '', subSubcategoryId = '') {
    const values = [];
    getProductAddingScopedRows(productAddingAttributes).forEach(attribute => {
        if (String(attribute.attribute_type || '').toLowerCase() !== type) return;
        if (categoryId) {
            const attributeCategoryId = String(attribute.category_id || '');
            if (attributeCategoryId && attributeCategoryId !== String(categoryId)) return;
        }
        if (subcategoryId) {
            const attributeSubcategoryId = String(attribute.subcategory_id || '');
            if (attributeSubcategoryId && attributeSubcategoryId !== String(subcategoryId)) return;
        }
        if (subSubcategoryId) {
            const attributeSubSubcategoryId = String(attribute.sub_sub_category_id || '');
            if (attributeSubSubcategoryId && attributeSubSubcategoryId !== String(subSubcategoryId)) return;
        }
        String(attribute.attribute_value || '').split(/[,;|]+/).forEach(value => {
            value = value.trim();
            if (value && !values.includes(value)) values.push(value);
        });
    });
    return values;
}

function buildProductAddingCategoryOptions() {
    let html = '<option value="">-- Select category --</option>';
    getProductAddingScopedRows(productAddingCategories).forEach(category => {
        html += `<option value="${escapeHtml(category.id)}" data-concern-id="${escapeHtml(category.concern_id || '')}">${escapeHtml(category.name)}</option>`;
    });
    return html;
}

function buildProductAddingAttributeSelect(type, id, label) {
    const values = getProductAddingAttributeValues(type);
    if (values.length === 0) {
        return `<div class="col-md-3">
            <label class="form-label small fw-semibold">${escapeHtml(label)} <span class="text-danger">*</span></label>
            <input type="text" name="finished_goods[${id}][${type}]" class="form-control form-control-sm fg-input-product-attribute" data-attribute-type="${escapeHtml(type)}" required>
        </div>`;
    }
    let options = `<option value="">-- Select ${escapeHtml(label.toLowerCase())} --</option>`;
    values.forEach(value => {
        options += `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`;
    });
    return `<div class="col-md-3">
        <label class="form-label small fw-semibold">${escapeHtml(label)} <span class="text-danger">*</span></label>
        <select name="finished_goods[${id}][${type}]" class="form-select form-select-sm fg-input-product-attribute" data-attribute-type="${escapeHtml(type)}" required>${options}</select>
    </div>`;
}

function getProductAddingMatrixAttributeTypes() {
    const reservedTypes = [
        'product', 'item_name', 'sku', 'item_code', 'variant_id', 'catalog_id',
        'quantity', 'qty', 'supplier_cost', 'unit_price', 'hsn', 'hsn_code', 'variant', 'style_code',
        'category_id', 'subcategory_id', 'sub_sub_category_id', 'catalog_gst_rate', 'tax_mode', 'gst',
        'gst_type', 'tax_input_type', 'gst_rate', 'sgst', 'cgst', 'igst', 'mrp', 'selling_price',
        'weight', 'allocated_qty', 'custom_attributes', 'catalog_style_name', 'variant_name'
    ];
    return [...new Set(getProductAddingScopedRows(productAddingAttributes)
        .map(attribute => String(attribute.attribute_type || '').toLowerCase().trim())
        .filter(type => type && !reservedTypes.includes(type)))];
}

function getProductAddingOtherAttributeTypes() {
    const reservedTypes = [
        'size', 'color', 'product', 'item_name', 'sku', 'item_code', 'variant_id', 'catalog_id',
        'quantity', 'qty', 'supplier_cost', 'unit_price', 'hsn', 'hsn_code', 'variant', 'style_code',
        'category_id', 'subcategory_id', 'sub_sub_category_id', 'catalog_gst_rate', 'tax_mode', 'gst',
        'gst_type', 'tax_input_type', 'gst_rate', 'sgst', 'cgst', 'igst', 'mrp', 'selling_price',
        'weight', 'allocated_qty', 'custom_attributes', 'catalog_style_name', 'variant_name'
    ];
    return [...new Set(getProductAddingScopedRows(productAddingAttributes)
        .map(attribute => String(attribute.attribute_type || '').toLowerCase().trim())
        .filter(type => type && type !== 'size' && type !== 'color' && !reservedTypes.includes(type)))];
}

function buildProductAddingVariantAttributeCell(type, cardId, rowIndex) {
    const row = document.getElementById(`fgVariantRow_${cardId}_${rowIndex}`);
    const categoryId = row?.querySelector('.fg-variant-category')?.value || '';
    const subcategoryId = row?.querySelector('.fg-variant-subcategory')?.value || '';
    const subSubcategoryId = row?.querySelector('.fg-variant-sub-subcategory')?.value || '';
    const values = getProductAddingAttributeValues(type, categoryId, subcategoryId, subSubcategoryId);
    const name = `finished_goods[${cardId}][variants][${rowIndex}][${type}]`;
    const required = false;
    const label = type.charAt(0).toUpperCase() + type.slice(1);
    if (values.length === 0) {
        return `<td><div class="fg-variant-attribute-control">
            <input type="text" name="${escapeHtml(name)}" class="form-control form-control-sm fg-variant-attribute" data-attribute-type="${escapeHtml(type)}" aria-label="${escapeHtml(label)}" ${required ? 'required' : ''} oninput="updateProductAddingVariantRow(${cardId}, ${rowIndex})">
            <button type="button" class="btn btn-outline-primary btn-sm" data-attribute-type="${escapeHtml(type)}" title="Save this ${escapeHtml(label.toLowerCase())} as an option" aria-label="Save ${escapeHtml(label.toLowerCase())} as an option" onclick="saveProductAddingAttributeValue(this.dataset.attributeType, this.parentElement.querySelector('[data-attribute-type]')?.value, ${cardId}, ${rowIndex})"><i class="bi bi-plus-lg"></i></button>
        </div></td>`;
    }
    let options = `<option value="">-- ${escapeHtml(label)} --</option>`;
    values.forEach(value => {
        options += `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`;
    });
    options += '<option value="__add_new_attribute_value__">+ Add New Value...</option>';
    return `<td><select name="${escapeHtml(name)}" class="form-select form-select-sm fg-variant-attribute" data-attribute-type="${escapeHtml(type)}" aria-label="${escapeHtml(label)}" ${required ? 'required' : ''} onchange="handleProductAddingAttributeChange(this, ${cardId}, ${rowIndex})">${options}</select></td>`;
}

let addAttrTargetCardId = null;

function promptAddProductAddingAttribute(fgId) {
    addAttrTargetCardId = fgId;
    const nameInput = document.getElementById('addAttrModalName');
    const valuesInput = document.getElementById('addAttrModalValues');
    const errorEl = document.getElementById('addAttrModalError');
    if (nameInput) nameInput.value = '';
    if (valuesInput) valuesInput.value = '';
    if (errorEl) {
        errorEl.textContent = '';
        errorEl.classList.add('d-none');
    }
    const modalEl = document.getElementById('addProductAttributeModal');
    if (modalEl && window.bootstrap?.Modal) {
        const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
        instance.show();
        setTimeout(() => nameInput?.focus(), 180);
    }
}

async function submitAddProductAttributeModal() {
    const fgId = addAttrTargetCardId;
    const nameInput = document.getElementById('addAttrModalName');
    const valuesInput = document.getElementById('addAttrModalValues');
    const errorEl = document.getElementById('addAttrModalError');
    const showErr = (msg) => {
        if (!errorEl) return;
        errorEl.textContent = msg;
        errorEl.classList.remove('d-none');
    };
    if (errorEl) errorEl.classList.add('d-none');

    const trimmedLabel = (nameInput?.value || '').trim();
    if (!trimmedLabel) {
        showErr('Enter an attribute name.');
        nameInput?.focus();
        return;
    }

    let type = trimmedLabel.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    if (/^[0-9]/.test(type)) type = `attribute_${type}`;
    if (!type || type.length > 50) {
        showErr('Use an attribute name that is at most 50 characters.');
        nameInput?.focus();
        return;
    }
    const reservedTypes = [
        'product', 'item_name', 'sku', 'item_code', 'variant_id', 'catalog_id',
        'quantity', 'qty', 'supplier_cost', 'unit_price', 'hsn', 'hsn_code', 'variant', 'style_code',
        'category_id', 'subcategory_id', 'sub_sub_category_id', 'catalog_gst_rate', 'tax_mode', 'gst',
        'gst_type', 'tax_input_type', 'gst_rate', 'sgst', 'cgst', 'igst', 'mrp', 'selling_price',
        'weight', 'allocated_qty', 'custom_attributes', 'catalog_style_name', 'variant_name'
    ];
    if (reservedTypes.includes(type)) {
        showErr('That name is reserved for a built-in product field. Use the existing field instead.');
        nameInput?.focus();
        return;
    }
    if (type !== 'size' && type !== 'color' && getProductAddingOtherAttributeTypes().includes(type)) {
        showErr('That attribute already exists. Use + Add Value instead.');
        nameInput?.focus();
        return;
    }

    const values = [...new Set((valuesInput?.value || '').split(/[,;|]+/).map(v => v.trim()).filter(Boolean))];
    if (!values.length) {
        showErr(`Enter at least one initial value for ${trimmedLabel}.`);
        valuesInput?.focus();
        return;
    }

    const btn = document.getElementById('btnCreateProductAttribute');
    const prevHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';
    }

    const firstRow = document.querySelector(`#finishedCard_${fgId} .fg-variant-row`);
    const firstRowIndex = firstRow ? parseInt(firstRow.dataset.rowIndex, 10) : null;
    try {
        for (const val of values) {
            await saveProductAddingAttributeValue(type, val, fgId, firstRowIndex, trimmedLabel);
        }
        const modalEl = document.getElementById('addProductAttributeModal');
        if (modalEl && window.bootstrap?.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = prevHtml;
        }
    }
}

function handleProductAddingAttributeChange(select, fgId, rowIndex) {
    if (select.value === '__add_new_attribute_value__') {
        const previousValue = select.dataset.previousValue || '';
        select.value = previousValue;
        const value = window.prompt(`New ${select.dataset.attributeType.replace(/_/g, ' ')} value:`);
        if (value === null) return;
        saveProductAddingAttributeValue(select.dataset.attributeType, value, fgId, rowIndex);
        return;
    }
    select.dataset.previousValue = select.value;
    updateProductAddingVariantRow(fgId, rowIndex);
}

function ensureProductAddingAttributeColumn(type, preferredCardId, preferredRowIndex, preferredValue) {
    document.querySelectorAll('#finishedGoodsContainer .finished-card').forEach(card => {
        const header = card.querySelector('.fg-variant-matrix thead tr');
        if (!header) return;
        const hasHeader = [...header.querySelectorAll('th[data-attribute-type]')]
            .some(cell => cell.dataset.attributeType === type);
        if (!hasHeader) {
            const th = document.createElement('th');
            th.dataset.attributeType = type;
            th.textContent = type.replace(/_/g, ' ').toUpperCase();
            const quantityHeader = [...header.children].find(cell => cell.textContent.trim().startsWith('QTY'));
            if (quantityHeader) header.insertBefore(th, quantityHeader);
        }

        card.querySelectorAll('.fg-variant-row').forEach(row => {
            const rowTypeControl = [...row.querySelectorAll('.fg-variant-attribute')]
                .find(control => control.dataset.attributeType === type);
            if (rowTypeControl) return;
            const rowIndex = parseInt(row.dataset.rowIndex, 10);
            const cellTemplate = document.createElement('template');
            cellTemplate.innerHTML = buildProductAddingVariantAttributeCell(type, parseInt(card.id.replace('finishedCard_', ''), 10), rowIndex).trim();
            const quantityCell = row.querySelector('.fg-variant-qty')?.closest('td');
            if (quantityCell && cellTemplate.content.firstElementChild) {
                row.insertBefore(cellTemplate.content.firstElementChild, quantityCell);
            }
        });
    });

    const targetRow = document.getElementById(`fgVariantRow_${preferredCardId}_${preferredRowIndex}`);
    const targetControl = targetRow && [...targetRow.querySelectorAll('.fg-variant-attribute')]
        .find(control => control.dataset.attributeType === type);
    if (targetControl && preferredValue) {
        targetControl.value = preferredValue;
        if (targetControl.tagName === 'SELECT') targetControl.dataset.previousValue = preferredValue;
    }
}

async function saveProductAddingAttributeValue(type, rawValue, fgId, rowIndex, attributeName = '') {
    const value = String(rawValue || '').trim();
    if (!value) {
        alert('Enter an attribute value before saving.');
        return;
    }
    if (value.length > 100) {
        alert('Attribute values must be 100 characters or fewer.');
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', document.querySelector('#formPurchaseBill input[name="csrf_token"]')?.value || '');
    formData.append('concern_id', String(getProductAddingConcernId()));
    formData.append('attribute_type', type);
    formData.append('attribute_name', attributeName || type.replace(/_/g, ' ').replace(/\b\w/g, character => character.toUpperCase()));
    formData.append('attribute_value', value);
    const targetRow = document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`);
    if (targetRow) {
        const categoryId = targetRow.querySelector('.fg-variant-category')?.value || '';
        const subcategoryId = targetRow.querySelector('.fg-variant-subcategory')?.value || '';
        const subSubcategoryId = targetRow.querySelector('.fg-variant-sub-subcategory')?.value || '';
        if (categoryId) formData.append('category_id', categoryId);
        if (subcategoryId) formData.append('subcategory_id', subcategoryId);
        if (subSubcategoryId) formData.append('sub_sub_category_id', subSubcategoryId);
    }

    try {
        const response = await fetch(quickAttributeSaveUrl, {
            method: 'POST',
            body: formData,
            headers: { Accept: 'application/json' }
        });
        const result = await response.json();
        if (!response.ok || !result.success || !result.attribute_value) {
            throw new Error(result.message || 'Unable to save this attribute value.');
        }

        if (!productAddingAttributes.some(attribute =>
            String(attribute.attribute_type || '').toLowerCase() === type &&
            String(attribute.attribute_value || '').trim().toLowerCase() === String(result.attribute_value).trim().toLowerCase() &&
            (parseInt(attribute.concern_id, 10) || 0) === (parseInt(result.concern_id, 10) || 0)
        )) {
            productAddingAttributes.push({
                concern_id: result.concern_id,
                category_id: result.category_id,
                subcategory_id: result.subcategory_id,
                sub_sub_category_id: result.sub_sub_category_id,
                attribute_type: result.attribute_type,
                attribute_name: result.attribute_name,
                attribute_value: result.attribute_value
            });
        }

        if (typeof refreshProductAddingAttributeSelectors === 'function') {
        refreshProductAddingAttributeSelectors();
    }
        ensureProductAddingAttributeColumn(type, fgId, rowIndex, result.attribute_value);
        document.querySelectorAll('#finishedGoodsContainer .fg-variant-row').forEach(row => {
            const rowCardId = parseInt(row.closest('.finished-card')?.id.replace('finishedCard_', ''), 10);
            const rowIndexValue = parseInt(row.dataset.rowIndex, 10);
            const control = [...row.querySelectorAll('.fg-variant-attribute')]
                .find(input => input.dataset.attributeType === type);
            if (!control) return;
            const oldValue = control.value;
            if (control.tagName === 'INPUT') {
                const cellTemplate = document.createElement('template');
                cellTemplate.innerHTML = buildProductAddingVariantAttributeCell(type, rowCardId, rowIndexValue).trim();
                const replacement = cellTemplate.content.firstElementChild;
                const newControl = replacement?.querySelector('.fg-variant-attribute');
                if (!replacement || !newControl) return;
                if (oldValue && ![...newControl.options].some(option => option.value === oldValue)) return;
                newControl.value = rowCardId === parseInt(fgId, 10) && rowIndexValue === parseInt(rowIndex, 10)
                    ? result.attribute_value
                    : oldValue;
                if (newControl.tagName === 'SELECT') newControl.dataset.previousValue = newControl.value;
                control.closest('td').replaceWith(replacement);
            } else {
                if (![...control.options].some(option => option.value === result.attribute_value)) {
                    const addNewOption = [...control.options].find(option => option.value === '__add_new_attribute_value__');
                    control.add(new Option(result.attribute_value, result.attribute_value), addNewOption ? addNewOption.index : undefined);
                }
                if (rowCardId === parseInt(fgId, 10) && rowIndexValue === parseInt(rowIndex, 10)) {
                    control.value = result.attribute_value;
                }
                control.dataset.previousValue = control.value;
            }
            updateProductAddingVariantRow(rowCardId, rowIndexValue);
        });
        syncFinishedTable();
    } catch (error) {
        alert(error.message || 'Unable to save this attribute value.');
    }
}

function buildProductAddingVariantRow(cardId, rowIndex) {
    const dynamicAttributeCells = getProductAddingMatrixAttributeTypes()
        .map(type => buildProductAddingVariantAttributeCell(type, cardId, rowIndex))
        .join('');
    const prefix = `finished_goods[${cardId}][variants][${rowIndex}]`;
    return `
        <tr id="fgVariantRow_${cardId}_${rowIndex}" class="fg-variant-row">
            <td><input type="text" name="${prefix}[variant_name]" class="form-control form-control-sm fg-variant-name" required oninput="this.dataset.userEdited='1'; syncFinishedTable()"></td>
            <td><div class="fg-variant-hierarchy-control">
                <select name="${prefix}[category_id]" class="form-select form-select-sm fg-variant-category" required onchange="onFinishedVariantCategoryChange(${cardId}, ${rowIndex})">${buildProductAddingCategoryOptions()}</select>
                <button type="button" class="btn btn-outline-primary btn-sm" title="Add category" aria-label="Add category" onclick="quickAddFinishedHierarchy('category', ${cardId}, ${rowIndex})"><i class="bi bi-plus-lg"></i></button>
            </div></td>
            <td><div class="fg-variant-hierarchy-control">
                <select name="${prefix}[subcategory_id]" class="form-select form-select-sm fg-variant-subcategory" onchange="onFinishedVariantSubcategoryChange(${cardId}, ${rowIndex})" disabled><option value="">-- Optional --</option></select>
                <button type="button" class="btn btn-outline-primary btn-sm fg-add-subcategory" title="Add subcategory" aria-label="Add subcategory" onclick="quickAddFinishedHierarchy('subcategory', ${cardId}, ${rowIndex})" disabled><i class="bi bi-plus-lg"></i></button>
            </div></td>
            <td><div class="fg-variant-hierarchy-control">
                <select name="${prefix}[sub_sub_category_id]" class="form-select form-select-sm fg-variant-sub-subcategory" onchange="onFinishedVariantSubSubcategoryChange(${cardId}, ${rowIndex})" disabled><option value="">-- Optional --</option></select>
                <button type="button" class="btn btn-outline-primary btn-sm fg-add-sub-subcategory" title="Add sub-subcategory" aria-label="Add sub-subcategory" onclick="quickAddFinishedHierarchy('subsubcategory', ${cardId}, ${rowIndex})" disabled><i class="bi bi-plus-lg"></i></button>
            </div></td>
            ${dynamicAttributeCells}
            <td>
                <input type="hidden" name="${prefix}[weight]" class="fg-variant-weight" value="0">
                <input type="number" step="1" min="1" name="${prefix}[quantity]" class="form-control form-control-sm text-center fg-variant-qty" required oninput="syncFinishedTable()">
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="text" name="${prefix}[hsn]" class="form-control font-monospace fg-variant-hsn" data-hsn-autocomplete data-hsn-type="HSN" ${getHsnInputAttributes()} required autocomplete="off" oninput="onFinishedVariantHsnChange(${cardId}, ${rowIndex})" onchange="onFinishedVariantHsnChange(${cardId}, ${rowIndex})">
                    <button type="button" class="btn btn-outline-primary" title="Add HSN" onclick="promptAddVariantHsn(${cardId}, ${rowIndex})"><i class="bi bi-plus-lg"></i></button>
                </div>
            </td>
            <td><div class="input-group input-group-sm">
                <input type="number" step="0.01" min="0" max="100" name="${prefix}[catalog_gst_rate]" class="form-control fg-variant-gst-rate" value="" placeholder="—" readonly aria-label="GST rate from HSN code">
                <span class="input-group-text">%</span>
            </div></td>
            <td><input type="number" step="0.01" min="0.01" name="${prefix}[mrp]" class="form-control form-control-sm fg-variant-mrp" required oninput="updateProductAddingVariantRow(${cardId}, ${rowIndex})"></td>
            <td><input type="number" step="0.01" min="0.01" name="${prefix}[selling_price]" class="form-control form-control-sm fg-variant-selling" required oninput="updateProductAddingVariantRow(${cardId}, ${rowIndex}); syncFinishedTable()"></td>
            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm" aria-label="Remove variant" onclick="removeFinishedVariantRow(${cardId}, ${rowIndex})"><i class="bi bi-trash3"></i></button></td>
        </tr>`;
}

let quickAddAttrTargetCardId = null;

function openQuickAddAttributeValueModal(cardId) {
    quickAddAttrTargetCardId = cardId;
    const container = document.getElementById(`fgVariantAttributeSelections_${cardId}`);
    const firstRow = document.getElementById(`fgVariantRow_${cardId}_1`);
    const categoryId = firstRow?.querySelector('.fg-variant-category')?.value || '';
    const subcategoryId = firstRow?.querySelector('.fg-variant-subcategory')?.value || '';
    const subSubcategoryId = firstRow?.querySelector('.fg-variant-sub-subcategory')?.value || '';
    const types = getProductAddingMatrixAttributeTypes();
    const rowsEl = document.getElementById('quickAddAttrRowsContainer');
    const subtitleEl = document.getElementById('quickAddAttrModalSubtitle');
    if (!rowsEl) return;

    let totalSelectedInCard = 0;
    const attrMeta = types.map(type => {
        const label = type.replace(/_/g, ' ').replace(/\b\w/g, character => character.toUpperCase());
        const control = container?.querySelector(`[data-variant-attribute-selector="${type}"]`);
        let selectedCount = 0;
        if (control) {
            selectedCount = control.matches('.fg-attribute-picker')
                ? control.querySelectorAll('[data-variant-attribute-option]:checked').length
                : control.value.split(/[,;|]+/).map(v => v.trim()).filter(Boolean).length;
        }
        if (selectedCount > 0) totalSelectedInCard++;
        const existingValues = getProductAddingAttributeValues(type, categoryId, subcategoryId, subSubcategoryId);
        return { type, label, selectedCount, existingCount: existingValues.length };
    });

    if (subtitleEl) {
        subtitleEl.textContent = `Garment #${cardId} • ${types.length} attribute${types.length === 1 ? '' : 's'} available, ${totalSelectedInCard} currently selected`;
    }

    rowsEl.innerHTML = attrMeta.map((item, idx) => {
        const isChecked = totalSelectedInCard > 0 ? item.selectedCount > 0 : idx === 0;
        return `<div class="border rounded-3 p-3 bg-white shadow-sm" data-quick-attr-row="${escapeHtml(item.type)}">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <label class="form-check d-flex align-items-center gap-2 mb-0" style="cursor:pointer;">
                    <input class="form-check-input mt-0" type="checkbox" data-quick-attr-check="${escapeHtml(item.type)}" ${isChecked ? 'checked' : ''} onchange="updateQuickAddAttributeModalSummary()">
                    <span class="fw-semibold text-dark">${escapeHtml(item.label)}</span>
                </label>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge ${item.selectedCount > 0 ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-light text-secondary border'}">
                        ${item.selectedCount} selected
                    </span>
                    <span class="badge bg-light text-muted border">${item.existingCount} saved</span>
                </div>
            </div>
            <input type="text" class="form-control form-control-sm" data-quick-attr-input="${escapeHtml(item.type)}"
                placeholder="Enter ${escapeHtml(item.label.toLowerCase())} value(s), comma-separated (e.g. Value 1, Value 2)"
                oninput="if(this.value.trim()){ const cb=this.closest('[data-quick-attr-row]')?.querySelector('[data-quick-attr-check]'); if(cb && !cb.checked){ cb.checked=true; updateQuickAddAttributeModalSummary(); } }">
        </div>`;
    }).join('');

    updateQuickAddAttributeModalSummary();
    const modalEl = document.getElementById('quickAddAttributeValueModal');
    if (modalEl && window.bootstrap?.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

function updateQuickAddAttributeModalSummary() {
    const checks = [...document.querySelectorAll('#quickAddAttrRowsContainer [data-quick-attr-check]')];
    const selectedCount = checks.filter(cb => cb.checked).length;
    const totalCount = checks.length;
    const badge = document.getElementById('quickAddAttrSummaryBadge');
    const footer = document.getElementById('quickAddAttrFooterStatus');
    if (badge) badge.textContent = `${selectedCount} / ${totalCount} attributes selected`;
    if (footer) footer.textContent = `${selectedCount} attribute${selectedCount === 1 ? '' : 's'} selected`;
}

async function submitQuickAddAttributeValues() {
    const cardId = quickAddAttrTargetCardId;
    if (!cardId) return;
    const rows = [...document.querySelectorAll('#quickAddAttrRowsContainer [data-quick-attr-row]')];
    const tasks = [];
    rows.forEach(row => {
        const type = row.dataset.quickAttrRow;
        const checked = row.querySelector('[data-quick-attr-check]')?.checked;
        const raw = row.querySelector('[data-quick-attr-input]')?.value || '';
        if (!checked || !raw.trim()) return;
        const label = type.replace(/_/g, ' ').replace(/\b\w/g, character => character.toUpperCase());
        const values = [...new Set(raw.split(/[,;|]+/).map(v => v.trim()).filter(Boolean))];
        values.forEach(val => tasks.push({ type, label, val }));
    });

    if (!tasks.length) {
        alert('Select at least one attribute and enter one or more values.');
        return;
    }

    const btn = document.getElementById('btnSaveQuickAttributeValues');
    const prevHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    }

    const firstRow = document.querySelector(`#finishedCard_${cardId} .fg-variant-row`);
    const firstRowIndex = firstRow ? parseInt(firstRow.dataset.rowIndex, 10) : null;
    const autoSelect = document.getElementById('quickAddAttrAutoSelect')?.checked !== false;

    try {
        for (const task of tasks) {
            await saveProductAddingAttributeValue(task.type, task.val, cardId, firstRowIndex, task.label);
        }
        if (autoSelect) {
            const selectorContainer = document.getElementById(`fgVariantAttributeSelections_${cardId}`);
            if (selectorContainer) {
                tasks.forEach(task => {
                    const control = selectorContainer.querySelector(`[data-variant-attribute-selector="${task.type}"]`);
                    if (!control) return;
                    if (control.matches('.fg-attribute-picker')) {
                        control.querySelectorAll('[data-variant-attribute-option]').forEach(opt => {
                            if (opt.value.trim().toLowerCase() === task.val.trim().toLowerCase() && !opt.checked) {
                                opt.checked = true;
                                updateProductAddingAttributeSelectionCount(opt);
                            }
                        });
                    }
                });
            }
        }
        const modalEl = document.getElementById('quickAddAttributeValueModal');
        if (modalEl && window.bootstrap?.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = prevHtml;
        }
    }
}

function buildProductAddingAttributeSelectionControls(cardId) {
    const firstRow = document.getElementById(`fgVariantRow_${cardId}_1`);
    const categoryId = firstRow?.querySelector('.fg-variant-category')?.value || '';
    const subcategoryId = firstRow?.querySelector('.fg-variant-subcategory')?.value || '';
    const subSubcategoryId = firstRow?.querySelector('.fg-variant-sub-subcategory')?.value || '';
    const types = getProductAddingMatrixAttributeTypes();
    return `<div class="row g-2 align-items-end mb-3" id="fgVariantAttributeSelections_${cardId}">
        ${types.map(type => {
            const values = getProductAddingAttributeValues(type, categoryId, subcategoryId, subSubcategoryId);
            const label = type.replace(/_/g, ' ').replace(/\b\w/g, character => character.toUpperCase());
            if (!values.length) {
                return `<div class="col-md-3">
                    <label class="form-label small fw-semibold">${escapeHtml(label)} values</label>
                    <input type="text" class="form-control form-control-sm" data-variant-attribute-selector="${escapeHtml(type)}" placeholder="Comma-separated values">
                </div>`;
            }
            return `<div class="col-md-3">
                <div class="form-label small fw-semibold mb-1">${escapeHtml(label)} values</div>
                <div class="fg-attribute-picker" data-variant-attribute-selector="${escapeHtml(type)}">
                    <div class="fg-attribute-option-list">
                        ${values.map(value => `<label class="fg-attribute-option">
                            <input type="checkbox" class="form-check-input" data-variant-attribute-option value="${escapeHtml(value)}" onchange="updateProductAddingAttributeSelectionCount(this)">
                            <span>${escapeHtml(value)}</span>
                        </label>`).join('')}
                    </div>
                    <div class="small text-muted mt-1"><span data-selection-count>0</span> selected</div>
                </div>
            </div>`;
        }).join('')}
        <div class="col-md-auto d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary" onclick="generateProductAddingVariantRows(${cardId})">
                <i class="bi bi-grid me-1"></i>Generate combinations
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="promptAddProductAddingAttribute(${cardId})">
                <i class="bi bi-plus-lg me-1"></i>Add Attribute
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openQuickAddAttributeValueModal(${cardId})">
                <i class="bi bi-plus-circle me-1"></i>Add Value
            </button>
        </div>
    </div>`;
}

function updateProductAddingAttributeSelectionCount(input) {
    const picker = input.closest('.fg-attribute-picker');
    if (!picker) return;
    const selected = picker.querySelectorAll('[data-variant-attribute-option]:checked').length;
    const count = picker.querySelector('[data-selection-count]');
    if (count) count.textContent = String(selected);
    input.closest('.fg-attribute-option')?.classList.toggle('is-selected', input.checked);
}

function refreshProductAddingAttributeSelectors() {
    document.querySelectorAll('#finishedGoodsContainer .finished-card').forEach(card => {
        const container = card.querySelector('[id^="fgVariantAttributeSelections_"]');
        if (!container) return;
        const selectedValues = new Map();
        container.querySelectorAll('[data-variant-attribute-selector]').forEach(control => {
            const type = control.dataset.variantAttributeSelector;
            const values = control.matches('.fg-attribute-picker')
                ? [...control.querySelectorAll('[data-variant-attribute-option]:checked')].map(option => option.value)
                : control.value.split(/[,;|]+/).map(value => value.trim()).filter(Boolean);
            selectedValues.set(type, values);
        });
        const replacementTemplate = document.createElement('template');
        replacementTemplate.innerHTML = buildProductAddingAttributeSelectionControls(
            parseInt(card.id.replace('finishedCard_', ''), 10)
        ).trim();
        const replacement = replacementTemplate.content.firstElementChild;
        if (!replacement) return;
        container.replaceWith(replacement);
        replacement.querySelectorAll('[data-variant-attribute-selector]').forEach(control => {
            const values = selectedValues.get(control.dataset.variantAttributeSelector) || [];
            if (control.matches('.fg-attribute-picker')) {
                control.querySelectorAll('[data-variant-attribute-option]').forEach(option => {
                    option.checked = values.includes(option.value);
                    option.closest('.fg-attribute-option')?.classList.toggle('is-selected', option.checked);
                });
                const count = control.querySelector('[data-selection-count]');
                if (count) count.textContent = String(values.length);
            } else {
                control.value = values.join(', ');
            }
        });
    });
}

function buildProductAddingFields(id) {
    if (!isProductAddingForm) return '';
    return `
        <div class="card border shadow-none rounded-3">
            <div class="card-body pb-2">
                ${buildProductAddingAttributeSelectionControls(id)}
            </div>
            <div class="fg-quick-fill-panel px-3 py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="fw-bold small text-dark"><i class="bi bi-lightning-charge-fill text-primary me-1"></i>Quick Fill (All Columns)</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="applyProductAddingQuickFill(${id})">
                        <i class="bi bi-check2-all me-1"></i>Apply to All Rows
                    </button>
                </div>
                <div class="fg-quick-fill-grid mt-2">
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickProductName_${id}">Product Name</label>
                        <input id="fgQuickProductName_${id}" type="text" class="form-control form-control-sm" data-quick-fill-field="product_name" placeholder="Product name...">
                    </div>
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickCategory_${id}">Category</label>
                        <select id="fgQuickCategory_${id}" class="form-select form-select-sm" data-quick-fill-hierarchy="category" onchange="updateProductAddingQuickFillHierarchy(${id}, 'category')">
                            ${buildProductAddingCategoryOptions()}
                        </select>
                    </div>
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickSubcategory_${id}">Subcategory</label>
                        <select id="fgQuickSubcategory_${id}" class="form-select form-select-sm" data-quick-fill-hierarchy="subcategory" onchange="updateProductAddingQuickFillHierarchy(${id}, 'subcategory')" disabled>
                            <option value="">-- Optional --</option>
                        </select>
                    </div>
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickSubSubcategory_${id}">Sub-subcategory</label>
                        <select id="fgQuickSubSubcategory_${id}" class="form-select form-select-sm" data-quick-fill-hierarchy="sub-subcategory" disabled>
                            <option value="">-- Optional --</option>
                        </select>
                    </div>
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickQty_${id}">Qty</label>
                        <input id="fgQuickQty_${id}" type="number" min="1" step="1" class="form-control form-control-sm" data-quick-fill-field="quantity" placeholder="Qty...">
                        <div class="form-check mt-1 d-none">
                            <input class="form-check-input" type="checkbox" id="fgQuickBalance_${id}" data-quick-fill-auto-balance>
                            <label class="form-check-label small" for="fgQuickBalance_${id}">Auto-Balance total</label>
                        </div>
                    </div>
                    <div class="d-none">
                        <label class="fg-quick-fill-label" for="fgQuickWeight_${id}">Weight (kg)</label>
                        <input id="fgQuickWeight_${id}" type="number" min="0" step="0.001" class="form-control form-control-sm" data-quick-fill-field="weight" placeholder="Weight...">
                    </div>
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickHsn_${id}">HSN Code</label>
                        <input id="fgQuickHsn_${id}" type="text" class="form-control form-control-sm font-monospace" data-quick-fill-field="hsn" data-hsn-autocomplete data-hsn-type="HSN" data-hsn-gst-target="#fgQuickGst_${id}" ${getHsnInputAttributes()} autocomplete="off" placeholder="HSN...">
                    </div>
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickGst_${id}">GST %</label>
                        <input id="fgQuickGst_${id}" type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" data-quick-fill-gst-preview readonly aria-label="GST rate from selected HSN" placeholder="Auto">
                    </div>
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickMrp_${id}">MRP (₹)</label>
                        <input id="fgQuickMrp_${id}" type="number" min="0.01" step="0.01" class="form-control form-control-sm" data-quick-fill-field="mrp" placeholder="MRP...">
                    </div>
                    <div>
                        <label class="fg-quick-fill-label" for="fgQuickSelling_${id}">Selling (₹)</label>
                        <input id="fgQuickSelling_${id}" type="number" min="0.01" step="0.01" class="form-control form-control-sm" data-quick-fill-field="selling" placeholder="Selling...">
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end align-items-center px-3 py-2 bg-white border-top">
                <button type="button" class="btn btn-primary btn-sm px-3" onclick="addFinishedVariantRow(${id})"><i class="bi bi-plus-circle me-1"></i>Add Row</button>
            </div>
            <div class="table-responsive fg-variant-matrix-scroll">
                <table class="table table-sm table-bordered table-hover align-middle mb-0 text-nowrap fg-variant-matrix">
                    <thead class="table-light">
                        <tr>
                            <th>PRODUCT VARIANT NAME *</th>
                            <th>CATEGORY *</th>
                            <th>SUBCATEGORY</th>
                            <th>SUB-SUBCATEGORY</th>
                            ${getProductAddingMatrixAttributeTypes().map(type => `<th data-attribute-type="${escapeHtml(type)}">${escapeHtml(type.toUpperCase())}</th>`).join('')}
                            <th>QTY *</th>
                            <th>HSN CODE *</th>
                            <th>GST %</th>
                            <th>MRP (₹) *</th>
                            <th>SELLING PRICE (₹) *</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="fgVariantTableBody_${id}"></tbody>
                </table>
            </div>
            <div class="card-footer bg-light py-2 px-3 d-flex justify-content-between small">
                <span class="text-muted">Selected category hierarchy is shared by all variants in this style.</span>
                <strong class="text-primary" id="fgVariantTotals_${id}">0 variants · 0 pcs</strong>
            </div>
        </div>`;
}

function initializeProductAddingFinishedRow(fgId) {
    const card = document.getElementById('finishedCard_' + fgId);
    if (!card || !isProductAddingForm) return;
    const newFields = card.querySelector('.fg-new-catalog-fields');
    const productInput = card.querySelector('.fg-input-product');
    if (newFields) {
        newFields.hidden = false;
        newFields.querySelectorAll('input, select').forEach(input => { input.disabled = false; });
    }
    ['.fg-input-qty', '.fg-input-cost', '.fg-input-mrp', '.fg-input-selling', '.fg-input-hsn'].forEach(selector => {
        const input = card.querySelector(selector);
        if (!input) return;
        input.disabled = true;
        input.required = false;
        const fieldContainer = input.closest('.col-md-3, .col-md-4, .col-md-6');
        if (fieldContainer) fieldContainer.hidden = true;
    });
    if (productInput) productInput.readOnly = false;
    if (newFields && !newFields.querySelector('.fg-variant-row')) {
        addFinishedVariantRow(fgId);
    }
    syncFinishedTable();
}

function refreshProductAddingConcernOptions() {
    if (!isProductAddingForm) return;
    document.querySelectorAll('#finishedGoodsContainer .finished-card').forEach(card => {
        const categorySelects = card.querySelectorAll('.fg-variant-category');
        if (categorySelects.length) {
            const rows = [...card.querySelectorAll('.fg-variant-row')];
            const currentCategory = rows[0]?.querySelector('.fg-variant-category')?.value || '';
            categorySelects.forEach(select => {
                select.innerHTML = buildProductAddingCategoryOptions();
                select.value = currentCategory;
            });
            if (rows[0]) {
                onFinishedVariantCategoryChange(parseInt(card.id.replace('finishedCard_', ''), 10), parseInt(rows[0].dataset.rowIndex, 10));
            }
        }
    });
}

document.getElementById('billConcernId')?.addEventListener('change', refreshProductAddingConcernOptions);

function addFinishedVariantRow(fgId, presetAttributes = {}) {
    const card = document.getElementById('finishedCard_' + fgId);
    const tbody = document.getElementById('fgVariantTableBody_' + fgId);
    if (!card || !tbody) return;
    const rowIndex = parseInt(card.dataset.nextVariantRow || '0', 10);
    card.dataset.nextVariantRow = String(rowIndex + 1);
    tbody.insertAdjacentHTML('beforeend', buildProductAddingVariantRow(fgId, rowIndex));
    const row = document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`);
    row.dataset.rowIndex = String(rowIndex);
    Object.entries(presetAttributes).forEach(([type, value]) => {
        const control = [...row.querySelectorAll('.fg-variant-attribute')]
            .find(input => input.dataset.attributeType === type);
        if (control) {
            control.value = value;
            if (control.tagName === 'SELECT') control.dataset.previousValue = value;
        }
    });
    updateProductAddingVariantRow(fgId, rowIndex);
    syncFinishedTable();
    return rowIndex;
}

function showInlineProductAddingMessage(fgId, message) {
    const card = document.getElementById('finishedCard_' + fgId);
    if (!card) return;
    let box = card.querySelector('.fg-variant-inline-message');
    if (!box) {
        box = document.createElement('div');
        box.className = 'alert alert-danger d-none py-2 px-3 mb-2 small fg-variant-inline-message';
        box.setAttribute('role', 'alert');
        box.setAttribute('aria-live', 'polite');
        card.querySelector('.card-body')?.prepend(box);
    }
    box.textContent = message;
    box.classList.remove('d-none');
}

function clearInlineProductAddingMessage(fgId) {
    document.getElementById('finishedCard_' + fgId)?.querySelector('.fg-variant-inline-message')?.classList.add('d-none');
}

function generateProductAddingVariantRows(fgId) {
    const card = document.getElementById('finishedCard_' + fgId);
    const selectorContainer = document.getElementById(`fgVariantAttributeSelections_${fgId}`);
    const tbody = document.getElementById('fgVariantTableBody_' + fgId);
    if (!card || !selectorContainer || !tbody) return;

    const dimensions = [...selectorContainer.querySelectorAll('[data-variant-attribute-selector]')]
        .map(control => {
            const type = control.dataset.variantAttributeSelector;
            const values = control.matches('.fg-attribute-picker')
                ? [...control.querySelectorAll('[data-variant-attribute-option]:checked')].map(option => option.value.trim()).filter(Boolean)
                : control.value.split(/[,;|]+/).map(value => value.trim()).filter(Boolean);
            return { type, values: [...new Set(values)] };
        })
        .filter(dimension => dimension.values.length);
    if (!dimensions.length) {
        showInlineProductAddingMessage(fgId, 'Select at least one attribute value from the selected catalog hierarchy.');
        return;
    }

    let combinations = [{}];
    for (const dimension of dimensions) {
        if (combinations.length * dimension.values.length > 100) {
            showInlineProductAddingMessage(fgId, 'This selection creates more than 100 variants. Reduce the selected values and try again.');
            return;
        }
        combinations = combinations.flatMap(combination =>
            dimension.values.map(value => ({ ...combination, [dimension.type]: value }))
        );
    }
    const rows = [...tbody.querySelectorAll('.fg-variant-row')];
    const starterRows = rows.filter(row => {
        const nameInput = row.querySelector('.fg-variant-name');
        const weight = parseFloat(row.querySelector('.fg-variant-weight')?.value) || 0;
        const hasAttributeValue = [...row.querySelectorAll('.fg-variant-attribute')]
            .some(control => control.value.trim() !== '');
        const hasVariantData = Boolean(
            row.querySelector('.fg-variant-category')?.value
            || row.querySelector('.fg-variant-subcategory')?.value
            || row.querySelector('.fg-variant-sub-subcategory')?.value
            || row.querySelector('.fg-variant-qty')?.value
            || row.querySelector('.fg-variant-hsn')?.value
            || row.querySelector('.fg-variant-cost')?.value
            || row.querySelector('.fg-variant-mrp')?.value
            || row.querySelector('.fg-variant-selling')?.value
            || weight > 0
            || hasAttributeValue
        );
        return nameInput?.dataset.userEdited !== '1' && !hasVariantData;
    });
    const starterRowSet = new Set(starterRows);
    const activeRows = rows.filter(row => !starterRowSet.has(row));
    if (activeRows.length + combinations.length > 100) {
        alert('A style can contain up to 100 variant rows.');
        return;
    }

    starterRows.forEach(row => row.remove());
    combinations.forEach(attributes => {
        addFinishedVariantRow(fgId, attributes);
        const createdRow = tbody.lastElementChild;
        if (createdRow) {
            createdRow.dataset.quickFillTarget = 'new';
        }
    });
}

function updateProductAddingQuickFillHierarchy(fgId, changedTier) {
    const panel = document.querySelector(`#finishedCard_${fgId} .fg-quick-fill-panel`);
    if (!panel) return;
    const category = panel.querySelector('[data-quick-fill-hierarchy="category"]');
    const subcategory = panel.querySelector('[data-quick-fill-hierarchy="subcategory"]');
    const subSubcategory = panel.querySelector('[data-quick-fill-hierarchy="sub-subcategory"]');
    if (!category || !subcategory || !subSubcategory) return;

    if (changedTier === 'category') {
        const categoryId = category.value;
        subcategory.innerHTML = '<option value="">-- Optional --</option>';
        getProductAddingScopedRows(productAddingSubcategories)
            .filter(item => String(item.category_id) === categoryId)
            .forEach(item => subcategory.add(new Option(item.name, item.id)));
        subcategory.disabled = !categoryId;
        subSubcategory.innerHTML = '<option value="">-- Optional --</option>';
        subSubcategory.disabled = true;
        return;
    }

    const subcategoryId = subcategory.value;
    subSubcategory.innerHTML = '<option value="">-- Optional --</option>';
    getProductAddingScopedRows(productAddingSubSubcategories)
        .filter(item => String(item.subcategory_id) === subcategoryId)
        .forEach(item => subSubcategory.add(new Option(item.name, item.id)));
    subSubcategory.disabled = !subcategoryId;
}

function applyProductAddingQuickFill(fgId) {
    const tbody = document.getElementById('fgVariantTableBody_' + fgId);
    const allRows = [...(tbody?.querySelectorAll('.fg-variant-row') || [])];
    const newlyCreatedRows = allRows.filter(row => row.dataset.quickFillTarget === 'new');
    const rows = newlyCreatedRows.length > 0 ? newlyCreatedRows : allRows;
    const panel = document.querySelector(`#finishedCard_${fgId} .fg-quick-fill-panel`);
    if (!rows.length || !panel) {
        alert('Add or generate variant rows before applying Quick Fill.');
        return;
    }
    const field = name => panel.querySelector(`[data-quick-fill-field="${name}"]`)?.value.trim() || '';
    const quickProductName = field('product_name');
    const quantityValue = field('quantity');
    const weightValue = field('weight');
    const hsnValue = field('hsn');
    const mrpValue = field('mrp');
    const sellingValue = field('selling');
    let quantities = [];
    if (quantityValue) {
        const quantity = parseInt(quantityValue, 10);
        const autoBalance = panel.querySelector('[data-quick-fill-auto-balance]')?.checked;
        if (!Number.isInteger(quantity) || quantity < 1) {
            alert('Enter a whole-number quantity greater than zero.');
            return;
        }
        if (autoBalance) {
            if (quantity < rows.length) {
                alert(`Auto-Balance needs a total quantity of at least ${rows.length} for these variant rows.`);
                return;
            }
            const baseQuantity = Math.floor(quantity / rows.length);
            const remainder = quantity % rows.length;
            quantities = rows.map((row, index) => baseQuantity + (index < remainder ? 1 : 0));
        } else {
            quantities = rows.map(() => quantity);
        }
    }

    if (weightValue !== '' && (!Number.isFinite(Number(weightValue)) || Number(weightValue) < 0)) {
        alert('Enter a valid non-negative weight.');
        return;
    }
    if (hsnValue && !findHsnMasterRow(hsnValue)) {
        alert('Select a registered HSN code so its GST rate can be filled automatically.');
        return;
    }
    const hasPricingInput = sellingValue !== '' || mrpValue !== '';
    if (hasPricingInput) {
        const invalidPricing = rows.some(row => {
            const selling = Number(sellingValue || row.querySelector('.fg-variant-selling')?.value || 0);
            const mrp = Number(mrpValue || row.querySelector('.fg-variant-mrp')?.value || 0);
            return !Number.isFinite(selling) || !Number.isFinite(mrp)
                || selling <= 0 || mrp <= selling;
        });
        if (invalidPricing) {
            alert('Every variant must satisfy Selling Price < MRP.');
            return;
        }
    }

    const categoryId = panel.querySelector('[data-quick-fill-hierarchy="category"]')?.value || '';
    const subcategoryId = panel.querySelector('[data-quick-fill-hierarchy="subcategory"]')?.value || '';
    const subSubcategoryId = panel.querySelector('[data-quick-fill-hierarchy="sub-subcategory"]')?.value || '';
    const selectedSubcategory = getProductAddingScopedRows(productAddingSubcategories)
        .find(item => String(item.id) === subcategoryId);
    const selectedSubSubcategory = getProductAddingScopedRows(productAddingSubSubcategories)
        .find(item => String(item.id) === subSubcategoryId);
    if ((subcategoryId && (!categoryId || String(selectedSubcategory?.category_id) !== categoryId))
        || (subSubcategoryId && (!subcategoryId || String(selectedSubSubcategory?.subcategory_id) !== subcategoryId))) {
        alert('Choose a valid category, subcategory, and sub-subcategory combination.');
        return;
    }

    if (categoryId) {
        const firstRow = rows[0];
        const firstRowIndex = parseInt(firstRow.dataset.rowIndex, 10);
        firstRow.querySelector('.fg-variant-category').value = categoryId;
        onFinishedVariantCategoryChange(fgId, firstRowIndex);
    }
    rows.forEach((row, index) => {
        if (quickProductName !== '') {
            const nameInput = row.querySelector('.fg-variant-name');
            if (nameInput) {
                nameInput.value = quickProductName.slice(0, 150);
                nameInput.dataset.userEdited = '1';
            }
        }
        if (categoryId) {
            const catSel = row.querySelector('.fg-variant-category');
            if (catSel) catSel.value = categoryId;
            fillFinishedVariantSubcategories(row, categoryId, subcategoryId);
            fillFinishedVariantSubSubcategories(row, subcategoryId, subSubcategoryId);
        }
        if (quantities.length) {
            row.querySelector('.fg-variant-qty').value = String(quantities[index]);
        }
        if (weightValue !== '') row.querySelector('.fg-variant-weight').value = weightValue;
        if (hsnValue !== '') {
            const hsn = row.querySelector('.fg-variant-hsn');
            hsn.value = hsnValue;
            resolveHsnInputMetadata(hsn);
            onFinishedVariantHsnChange(fgId, parseInt(row.dataset.rowIndex, 10));
        }
        if (mrpValue !== '') row.querySelector('.fg-variant-mrp').value = mrpValue;
        if (sellingValue !== '') row.querySelector('.fg-variant-selling').value = sellingValue;
        delete row.dataset.quickFillTarget;
        updateProductAddingVariantRow(fgId, parseInt(row.dataset.rowIndex, 10));
    });
    syncFinishedTable();
}

function removeFinishedVariantRow(fgId, rowIndex) {
    const tbody = document.getElementById('fgVariantTableBody_' + fgId);
    if (!tbody) return;
    if (tbody.querySelectorAll('.fg-variant-row').length <= 1) {
        alert('A new finished-goods style needs at least one variant row.');
        return;
    }
    document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`)?.remove();
    syncFinishedTable();
}

function updateProductAddingVariantRow(fgId, rowIndex) {
    const card = document.getElementById('finishedCard_' + fgId);
    const row = document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`);
    if (!card || !row) return;
    const variantName = row.querySelector('.fg-variant-name');
    const productName = card.querySelector('.fg-input-product')?.value.trim() || '';
    if (variantName && !variantName.dataset.userEdited) {
        variantName.value = productName.slice(0, 150);
    }
    const sellingInput = row.querySelector('.fg-variant-selling');
    const mrpInput = row.querySelector('.fg-variant-mrp');
    const selling = parseFloat(sellingInput?.value) || 0;
    const mrp = parseFloat(mrpInput?.value) || 0;
    sellingInput?.setCustomValidity(mrp > 0 && selling >= mrp
        ? 'Selling price must be lower than MRP.'
        : '');
    mrpInput?.setCustomValidity(selling > 0 && mrp <= selling ? 'MRP must be greater than selling price.' : '');
    const profit = row.querySelector('.fg-variant-profit');
    if (profit) {
        const value = selling;
        profit.textContent = `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        profit.classList.toggle('text-success', value > 0);
        profit.classList.toggle('text-danger', value < 0);
    }
    const hierarchyKey = row.querySelector('.fg-variant-hierarchy-key');
    if (hierarchyKey) {
        const category = productAddingCategories.find(item => String(item.id) === row.querySelector('.fg-variant-category')?.value);
        const subcategory = productAddingSubcategories.find(item => String(item.id) === row.querySelector('.fg-variant-subcategory')?.value);
        const subSubcategory = productAddingSubSubcategories.find(item => String(item.id) === row.querySelector('.fg-variant-sub-subcategory')?.value);
        const initials = [category?.name, subcategory?.name, subSubcategory?.name]
            .filter(Boolean)
            .map(name => String(name).replace(/[^a-z0-9]/gi, '').slice(0, 2).toUpperCase())
            .join('-');
        hierarchyKey.textContent = initials || '—';
        hierarchyKey.title = [category?.name, subcategory?.name, subSubcategory?.name].filter(Boolean).join(' > ');
    }
}

function fillFinishedVariantSubcategories(row, categoryId, selectedSubcategoryId = '') {
    const subcategory = row.querySelector('.fg-variant-subcategory');
    const subSubcategory = row.querySelector('.fg-variant-sub-subcategory');
    if (!subcategory || !subSubcategory) return;
    subcategory.innerHTML = '<option value="">-- Optional --</option>';
    getProductAddingScopedRows(productAddingSubcategories)
        .filter(item => parseInt(item.category_id, 10) === parseInt(categoryId, 10))
        .forEach(item => subcategory.add(new Option(item.name, item.id)));
    subcategory.value = selectedSubcategoryId || '';
    subcategory.disabled = !categoryId;
    const addSubcategoryButton = row.querySelector('.fg-add-subcategory');
    if (addSubcategoryButton) addSubcategoryButton.disabled = !categoryId;
    fillFinishedVariantSubSubcategories(row, subcategory.value);
}

function fillFinishedVariantSubSubcategories(row, subcategoryId, selectedSubSubcategoryId = '') {
    const subSubcategory = row.querySelector('.fg-variant-sub-subcategory');
    if (!subSubcategory) return;
    subSubcategory.innerHTML = '<option value="">-- Optional --</option>';
    getProductAddingScopedRows(productAddingSubSubcategories)
        .filter(item => parseInt(item.subcategory_id, 10) === parseInt(subcategoryId, 10))
        .forEach(item => subSubcategory.add(new Option(item.name, item.id)));
    subSubcategory.value = selectedSubSubcategoryId || '';
    subSubcategory.disabled = !subcategoryId;
    const addSubSubcategoryButton = row.querySelector('.fg-add-sub-subcategory');
    if (addSubSubcategoryButton) addSubSubcategoryButton.disabled = !subcategoryId;
}

function onFinishedVariantCategoryChange(fgId, rowIndex) {
    const sourceRow = document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`);
    if (!sourceRow) return;
    const categoryId = sourceRow.querySelector('.fg-variant-category')?.value || '';
    const categoryData = getProductAddingScopedRows(productAddingCategories)
        .find(item => String(item.id) === categoryId);
    fillFinishedVariantSubcategories(sourceRow, categoryId);
    const hsnInput = sourceRow.querySelector('.fg-variant-hsn');
    if (categoryData?.default_hsn && hsnInput && !hsnInput.value) {
        const matchedHsn = findHsnMasterRow(categoryData.default_hsn);
        if (matchedHsn) {
            setHsnInputValue(hsnInput, matchedHsn);
        } else {
            hsnInput.value = categoryData.default_hsn;
            hsnInput.dataset.hsnGstRate = categoryData.default_gst_rate ?? '';
        }
        if ((hsnInput.dataset.hsnGstRate === '' || hsnInput.dataset.hsnGstRate === undefined)
            && categoryData.default_gst_rate !== null && categoryData.default_gst_rate !== '') {
            hsnInput.dataset.hsnGstRate = categoryData.default_gst_rate;
        }
        onFinishedVariantHsnChange(fgId, rowIndex);
    }
    updateProductAddingVariantRow(fgId, rowIndex);
}

function onFinishedVariantSubcategoryChange(fgId, rowIndex) {
    const sourceRow = document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`);
    if (!sourceRow) return;
    const subcategoryId = sourceRow.querySelector('.fg-variant-subcategory')?.value || '';
    fillFinishedVariantSubSubcategories(sourceRow, subcategoryId);
    updateProductAddingVariantRow(fgId, rowIndex);
}

function onFinishedVariantSubSubcategoryChange(fgId, rowIndex) {
    const sourceRow = document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`);
    if (!sourceRow) return;
    updateProductAddingVariantRow(fgId, rowIndex);
}

async function quickAddFinishedHierarchy(tier, fgId, rowIndex) {
    const row = document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`);
    if (!row) return;
    const categoryId = row.querySelector('.fg-variant-category')?.value || '';
    const subcategoryId = row.querySelector('.fg-variant-subcategory')?.value || '';
    if (tier !== 'category' && !categoryId) {
        alert('Select a category first.');
        return;
    }
    if (tier === 'subsubcategory' && !subcategoryId) {
        alert('Select a subcategory first.');
        return;
    }

    const labels = {
        category: 'category',
        subcategory: 'subcategory',
        subsubcategory: 'sub-subcategory'
    };
    const name = window.prompt(`New ${labels[tier]} name:`);
    if (name === null) return;
    if (!name.trim()) {
        alert('Enter a name before adding this category.');
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', document.querySelector('#formPurchaseBill input[name="csrf_token"]')?.value || '');
    formData.append('concern_id', String(getProductAddingConcernId()));
    formData.append('tier', tier);
    formData.append('name', name.trim());
    if (categoryId) formData.append('category_id', categoryId);
    if (subcategoryId) formData.append('subcategory_id', subcategoryId);

    try {
        const response = await fetch(quickCategorySaveUrl, {
            method: 'POST',
            body: formData,
            headers: { Accept: 'application/json' }
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || `Unable to add ${labels[tier]}.`);
        }

        const card = document.getElementById('finishedCard_' + fgId);
        const rows = [...(card?.querySelectorAll('.fg-variant-row') || [])];
        if (tier === 'category') {
            productAddingCategories.push({
                id: result.id,
                concern_id: result.concern_id,
                name: result.name
            });
            rows.forEach(variantRow => {
                const select = variantRow.querySelector('.fg-variant-category');
                select.innerHTML = buildProductAddingCategoryOptions();
                select.value = String(result.id);
            });
            onFinishedVariantCategoryChange(fgId, rowIndex);
        } else if (tier === 'subcategory') {
            productAddingSubcategories.push({
                id: result.id,
                concern_id: result.concern_id,
                category_id: result.category_id,
                name: result.name
            });
            rows.forEach(variantRow => {
                fillFinishedVariantSubcategories(variantRow, categoryId, String(result.id));
            });
            onFinishedVariantSubcategoryChange(fgId, rowIndex);
        } else {
            productAddingSubSubcategories.push({
                id: result.id,
                concern_id: result.concern_id,
                subcategory_id: result.parent_id,
                name: result.name
            });
            rows.forEach(variantRow => {
                fillFinishedVariantSubSubcategories(variantRow, subcategoryId, String(result.id));
            });
            onFinishedVariantSubSubcategoryChange(fgId, rowIndex);
        }
    } catch (error) {
        alert(error.message || `Unable to add ${labels[tier]}.`);
    }
}

function onFinishedVariantHsnChange(fgId, rowIndex) {
    const row = document.getElementById(`fgVariantRow_${fgId}_${rowIndex}`);
    if (!row) return;
    const hsnInput = row.querySelector('.fg-variant-hsn');
    const gstRateInput = row.querySelector('.fg-variant-gst-rate');
    const rate = hsnInput?.dataset.hsnGstRate;
    if (gstRateInput) gstRateInput.value = rate !== undefined && rate !== '' ? rate : '';
    syncFinishedTable();
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
    formData.append('concern_id', '<?= (int) $selectedCid ?>');
    <?php endif; ?>

    fetch('<?= $rollSpecSaveUrl ?>', {
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

function getGlobalTaxSettings() {
    return {
        taxMode: document.getElementById('globalTaxMode')?.value || 'Exclude GST',
        gstType: document.getElementById('globalGstType')?.value || 'Exempted',
        taxInputType: document.getElementById('globalTaxInputType')?.value || 'Percentage',
    };
}

function syncGlobalTaxControlsToAllRows() {
    const { taxMode, gstType, taxInputType } = getGlobalTaxSettings();
    const wrap = document.getElementById('globalTaxInputTypeWrap');
    if (wrap) wrap.style.display = gstType === 'Exempted' ? 'none' : '';

    document.querySelectorAll('.roll-card').forEach(card => {
        const id = card.id.replace('rollCard_', '');
        if (card.querySelector('.roll-input-tax-mode')) card.querySelector('.roll-input-tax-mode').value = taxMode;
        if (card.querySelector('.roll-input-gst')) card.querySelector('.roll-input-gst').value = gstType;
        if (card.querySelector('.roll-input-tax-type')) card.querySelector('.roll-input-tax-type').value = taxInputType;
        onRollGstTypeChange(id);
    });
    document.querySelectorAll('.other-card').forEach(card => {
        const id = card.id.replace('otherCard_', '');
        if (card.querySelector('.other-input-tax-mode')) card.querySelector('.other-input-tax-mode').value = taxMode;
        if (card.querySelector('.other-input-gst')) card.querySelector('.other-input-gst').value = gstType;
        if (card.querySelector('.other-input-tax-type')) card.querySelector('.other-input-tax-type').value = taxInputType;
        onOtherGstTypeChange(id);
    });
    document.querySelectorAll('.finished-card').forEach(card => {
        const id = card.id.replace('finishedCard_', '');
        if (card.querySelector('.fg-input-tax-mode')) card.querySelector('.fg-input-tax-mode').value = taxMode;
        if (card.querySelector('.fg-input-gst')) card.querySelector('.fg-input-gst').value = gstType;
        if (card.querySelector('.fg-input-tax-type')) card.querySelector('.fg-input-tax-type').value = taxInputType;
        if (typeof onFinishedGstTypeChange === 'function') onFinishedGstTypeChange(id);
    });
    document.querySelectorAll('.simple-purchase-card').forEach(card => {
        const id = card.id.replace('simpleCard_', '');
        if (card.querySelector('.simple-input-tax-mode')) card.querySelector('.simple-input-tax-mode').value = taxMode;
        if (card.querySelector('.simple-input-gst')) card.querySelector('.simple-input-gst').value = gstType;
        if (card.querySelector('.simple-input-tax-type')) card.querySelector('.simple-input-tax-type').value = taxInputType;
        onSimpleGstTypeChange(id);
    });
}

function addRollRow() {
    rollCounter++;
    document.getElementById('itemsValidationError')?.classList.add('d-none');
    const rollCode = generateRollCode(rollCounter);
    const container = document.getElementById('rollsContainer');
    document.getElementById('noRollsPlaceholder')?.classList.add('d-none');
    const globalTax = getGlobalTaxSettings();
    const tr = document.createElement('tr');
    tr.className = 'roll-card';
    tr.id = 'rollCard_' + rollCounter;

    tr.innerHTML = `
        <td class="text-center px-1">
            <div class="fw-bold text-dark font-monospace small">${rollCounter}</div>
            <span class="badge bg-dark font-monospace roll-code-badge d-none" id="rollCodeBadge_${rollCounter}">${rollCode}</span>
            <input type="hidden" name="rolls[${rollCounter}][existing_photo]" value="">
        </td>
        <td class="px-1">
            <input type="text" name="rolls[${rollCounter}][roll_code]" class="form-control form-control-sm px-1 font-monospace text-uppercase roll-input-code" placeholder="Fabric name" value="" required oninput="updateRollHeaderCode(${rollCounter}); syncRollsTable()">
        </td>
        <td class="px-1">
            <input type="text" name="rolls[${rollCounter}][color]" list="rollColorList" class="form-control form-control-sm px-1 text-uppercase roll-input-color" placeholder="Color" value="" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
            <div class="roll-stock-notice small mt-1" id="rollStockNotice_${rollCounter}" style="display: none;"></div>
        </td>
        <td class="px-1">
            <input type="text" name="rolls[${rollCounter}][pattern]" list="rollPatternList" class="form-control form-control-sm px-1 text-uppercase roll-input-pattern" placeholder="Pattern" value="" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
        </td>
        <td class="px-1">
            <input type="number" min="1" step="1" name="rolls[${rollCounter}][rq]" class="form-control form-control-sm px-1 text-end roll-input-rq" placeholder="1" value="1" required oninput="onRollColorOrPatternChange(${rollCounter}); syncRollsTable()">
        </td>
        <td class="px-1">
            <input type="number" step="0.01" min="0.01" name="rolls[${rollCounter}][weight]" class="form-control form-control-sm px-1 text-end roll-input-weight" placeholder="0.00" value="" required oninput="syncRollsTable()">
        </td>
        <td class="px-1">
            <input type="number" step="0.01" min="0.01" name="rolls[${rollCounter}][price]" class="form-control form-control-sm px-1 text-end roll-input-price" placeholder="0.00" value="" required oninput="syncRollsTable()">
        </td>
        <td class="px-1">
            <div class="input-group input-group-sm">
                <input type="text" name="rolls[${rollCounter}][hsn]" class="form-control form-control-sm px-1 font-monospace roll-input-hsn" placeholder="HSN" data-hsn-autocomplete data-hsn-type="HSN" ${getHsnInputAttributes()} autocomplete="off" oninput="onRollHsnInput(${rollCounter})" onchange="onRollHsnChange(${rollCounter})">
                <button type="button" class="btn btn-outline-primary px-1" title="Add HSN" onclick="promptAddHsn(${rollCounter})">+</button>
            </div>
            <small class="hsn-rate-note text-muted d-block"></small>
        </td>
        <td class="px-1">
            <input type="hidden" name="rolls[${rollCounter}][tax_mode]" class="roll-input-tax-mode" value="${escapeHtml(globalTax.taxMode)}">
            <input type="hidden" name="rolls[${rollCounter}][gst]" class="roll-input-gst" value="${escapeHtml(globalTax.gstType)}">
            <input type="hidden" name="rolls[${rollCounter}][tax_input_type]" class="roll-input-tax-type" value="${escapeHtml(globalTax.taxInputType)}">
            <span class="badge bg-light text-dark border ${globalTax.gstType === 'Exempted' ? '' : 'd-none'}" id="rollExemptBadge_${rollCounter}">Exempted</span>
            <div class="${globalTax.gstType === 'Exempted' ? 'd-none' : ''} roll-gst-dynamic-container" id="gstDynamic_${rollCounter}">
                <div class="d-flex gap-1">
                    <div class="flex-fill roll-sgst-col" id="sgstCol_${rollCounter}">
                        <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][sgst]" class="form-control form-control-sm px-1 roll-input-sgst" value="0" placeholder="SGST" title="SGST" oninput="syncRollsTable()">
                    </div>
                    <div class="flex-fill roll-cgst-col" id="cgstCol_${rollCounter}">
                        <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][cgst]" class="form-control form-control-sm px-1 roll-input-cgst" value="0" placeholder="CGST" title="CGST" oninput="syncRollsTable()">
                    </div>
                    <div class="flex-fill roll-igst-col d-none" id="igstCol_${rollCounter}">
                        <input type="number" step="0.01" min="0" name="rolls[${rollCounter}][igst]" class="form-control form-control-sm px-1 roll-input-igst" value="0" placeholder="IGST" title="IGST" oninput="syncRollsTable()">
                    </div>
                </div>
            </div>
        </td>
        <td class="px-1 roll-photo-td">
            <input type="file" name="rolls[${rollCounter}][photo]" id="rollPhotoInput_${rollCounter}" class="visually-hidden roll-input-photo" accept=".jpg,.jpeg,.png,.webp" onchange="previewRollPhoto(this, ${rollCounter})">
            <label for="rollPhotoInput_${rollCounter}" id="rollPhotoTrigger_${rollCounter}" class="roll-photo-upload-btn" title="Upload roll photo">
                <i class="bi bi-cloud-arrow-up"></i>
                <span>Upload</span>
            </label>
            <div class="roll-photo-preview-inline d-none" id="photoPreview_${rollCounter}"></div>
        </td>
        <td class="text-center px-1">
            <button type="button" class="btn btn-outline-danger btn-sm roll-delete-btn" title="Delete Roll" onclick="removeRollRow(${rollCounter})">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    container.appendChild(tr);
    onRollGstTypeChange(rollCounter);
}

function removeRollRow(id) {
    const card = document.getElementById('rollCard_' + id);
    if (card) {
        card.remove();
        syncRollsTable();
    }
}

function calculateItemTax(card, prefix, qty, price, hsnRateOverride) {
    const taxMode = card.querySelector(`.${prefix}-input-tax-mode`)?.value || 'Exclude GST';
    const gstType = card.querySelector(`.${prefix}-input-gst`)?.value || 'Exempted';
    const taxType = card.querySelector(`.${prefix}-input-tax-type`)?.value || 'Percentage';
    const isPct = (taxType === 'Percentage');

    let sgstVal = parseFloat(card.querySelector(`.${prefix}-input-sgst`)?.value) || 0;
    let cgstVal = parseFloat(card.querySelector(`.${prefix}-input-cgst`)?.value) || 0;
    let igstVal = parseFloat(card.querySelector(`.${prefix}-input-igst`)?.value) || 0;
    const hsnRateText = hsnRateOverride === undefined ? '' : hsnRateOverride;
    const hsnRate = hsnRateText !== '' ? parseFloat(hsnRateText) : null;
    if (prefix === 'fg' && isPct && hsnRate !== null && Number.isFinite(hsnRate)) {
        if (gstType === 'Both (CGST and SGST)') {
            sgstVal = hsnRate / 2;
            cgstVal = hsnRate / 2;
        } else if (gstType === 'CGST') {
            cgstVal = hsnRate / 2;
        } else if (gstType === 'SGST') {
            sgstVal = hsnRate / 2;
        } else if (gstType === 'IGST') {
            igstVal = hsnRate;
        }
    }

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
    const globalTax = getGlobalTaxSettings();

    const tr = document.createElement('tr');
    tr.className = 'other-card';
    tr.id = 'otherCard_' + otherCounter;
    tr.innerHTML = `
        <td class="text-center px-1 fw-bold text-dark font-monospace small">${otherCounter}</td>
        <td class="px-1">
            <input type="hidden" name="garment_other[${otherCounter}][product_no]" class="other-input-code" value="">
            <input type="text" name="garment_other[${otherCounter}][item_name]" class="form-control form-control-sm px-1 other-input-name" placeholder="Item name" value="" required oninput="syncOtherTable()">
        </td>
        <td class="px-1">
            <input type="number" step="1" min="1" name="garment_other[${otherCounter}][quantity]" class="form-control form-control-sm px-1 text-end other-input-qty" placeholder="0" value="" required oninput="syncOtherTable()">
        </td>
        <td class="px-1">
            <div class="input-group input-group-sm">
                <select name="garment_other[${otherCounter}][unit]" class="form-select form-select-sm px-1 other-input-unit" onchange="onUnitChange(this, ${otherCounter}); syncOtherTable()">
                    ${getUnitOptionsHtml('Pieces')}
                </select>
                <button type="button" class="btn btn-outline-primary px-1" title="Add Unit" onclick="promptAddUnit(${otherCounter})">+</button>
            </div>
        </td>
        <td class="px-1">
            <input type="number" step="0.01" min="0.01" name="garment_other[${otherCounter}][unit_price]" class="form-control form-control-sm px-1 text-end other-input-price" placeholder="0.00" value="" required oninput="syncOtherTable()">
        </td>
        <td class="px-1">
            <div class="input-group input-group-sm">
                <input type="text" name="garment_other[${otherCounter}][hsn]" class="form-control form-control-sm px-1 font-monospace other-input-hsn" placeholder="HSN" data-hsn-autocomplete data-hsn-type="HSN" ${getHsnInputAttributes()} autocomplete="off" oninput="onOtherHsnInput(${otherCounter})" onchange="onOtherHsnChange(${otherCounter})">
                <button type="button" class="btn btn-outline-primary px-1" title="Add HSN" onclick="promptAddHsn(${otherCounter}, 'other')">+</button>
            </div>
            <small class="hsn-rate-note text-muted d-block"></small>
        </td>
        <td class="px-1">
            <input type="hidden" name="garment_other[${otherCounter}][tax_mode]" class="other-input-tax-mode" value="${escapeHtml(globalTax.taxMode)}">
            <input type="hidden" name="garment_other[${otherCounter}][gst]" class="other-input-gst" value="${escapeHtml(globalTax.gstType)}">
            <input type="hidden" name="garment_other[${otherCounter}][tax_input_type]" class="other-input-tax-type" value="${escapeHtml(globalTax.taxInputType)}">
            <span class="badge bg-light text-dark border ${globalTax.gstType === 'Exempted' ? '' : 'd-none'}" id="otherExemptBadge_${otherCounter}">Exempted</span>
            <div class="${globalTax.gstType === 'Exempted' ? 'd-none' : ''} other-gst-dynamic-container" id="otherGstDynamic_${otherCounter}">
                <div class="d-flex gap-1">
                    <div class="flex-fill other-sgst-col" id="otherSgstCol_${otherCounter}">
                        <input type="number" step="0.01" min="0" name="garment_other[${otherCounter}][sgst]" class="form-control form-control-sm px-1 other-input-sgst" value="" placeholder="SGST" title="SGST" oninput="syncOtherTable()">
                    </div>
                    <div class="flex-fill other-cgst-col" id="otherCgstCol_${otherCounter}">
                        <input type="number" step="0.01" min="0" name="garment_other[${otherCounter}][cgst]" class="form-control form-control-sm px-1 other-input-cgst" value="" placeholder="CGST" title="CGST" oninput="syncOtherTable()">
                    </div>
                    <div class="flex-fill d-none other-igst-col" id="otherIgstCol_${otherCounter}">
                        <input type="number" step="0.01" min="0" name="garment_other[${otherCounter}][igst]" class="form-control form-control-sm px-1 other-input-igst" value="" placeholder="IGST" title="IGST" oninput="syncOtherTable()">
                    </div>
                </div>
            </div>
        </td>
        <td class="text-center px-1">
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" title="Delete Supply" onclick="removeOtherRow(${otherCounter})">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    container.appendChild(tr);
    onOtherGstTypeChange(otherCounter);
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
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-3 text-muted">Add garment supplies to see their purchase details.</td></tr>';
        document.getElementById('overviewOtherVal').textContent = '₹0.00';
        recalcOverallSummary();
        return;
    }

    document.getElementById('noSuppliesPlaceholder').classList.add('d-none');
    let totalOtherBase = 0;
    let totalOtherTax = 0;

    cards.forEach(function(card, idx) {
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
    const catalogFields = buildProductAddingFields(finishedCounter);
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
            ${isProductAddingForm
                ? `<input type="hidden" name="finished_goods[${finishedCounter}][product]" class="fg-input-product" value="">`
                : `<!-- Row 1: Product, Qty, Unit Price of Supplier Cost -->
            <div class="row g-2 mb-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold mb-1">Product / Style Name <span class="text-danger">*</span></label>
                    <input type="text" name="finished_goods[${finishedCounter}][product]" class="form-control form-control-sm text-uppercase fg-input-product" placeholder="e.g. RED SHIRT" value="" required oninput="document.querySelectorAll('#finishedCard_${finishedCounter} .fg-variant-row').forEach(row => updateProductAddingVariantRow(${finishedCounter}, parseInt(row.dataset.rowIndex, 10))); syncFinishedTable()">
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
            </div>`
            }
            ${catalogFields ? `<div class="fg-new-catalog-fields border rounded-3 bg-light p-3 mb-3" hidden>${catalogFields}</div>` : ''}

            <!-- Row 2: HSN, Tax Mode, GST & Dynamic Fields -->
            <div class="row g-2 align-items-end ${isProductAddingForm ? 'd-none' : ''}">
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">HSN Code</label>
                        <a href="javascript:void(0)" class="text-decoration-none small text-primary fw-semibold" onclick="promptAddHsn(${finishedCounter}, 'finished')">+ Add HSN</a>
                    </div>
                    <input type="text" name="finished_goods[${finishedCounter}][hsn]" class="form-control form-control-sm font-monospace fg-input-hsn" data-hsn-autocomplete data-hsn-type="HSN" ${getHsnInputAttributes()} ${isProductAddingForm ? 'required' : ''} autocomplete="off" oninput="onFinishedHsnInput(${finishedCounter})" onchange="onFinishedHsnChange(${finishedCounter})">
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
    if (isProductAddingForm) {
        initializeProductAddingFinishedRow(finishedCounter);
    }
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
    let lineCount = 0;
    cards.forEach(card => {
        if (isProductAddingForm) {
            const rows = [...card.querySelectorAll('.fg-variant-row')];
            const firstVariantName = rows[0]?.querySelector('.fg-variant-name')?.value?.trim() || '';
            const productHidden = card.querySelector('.fg-input-product');
            if (productHidden) {
                productHidden.value = firstVariantName;
            }
            const totalQuantity = rows.reduce((sum, row) => sum + (parseInt(row.querySelector('.fg-variant-qty')?.value, 10) || 0), 0);
            lineCount += rows.length;
            const totals = card.querySelector('[id^="fgVariantTotals_"]');
            if (totals) totals.textContent = `${rows.length} variants · ${totalQuantity} pcs`;
        } else {
            lineCount++;
        }
    });
    document.getElementById('finishedBadgeCount').textContent = lineCount;
    document.getElementById('finishedItemsCountBadge').textContent = lineCount + ' Items';
    document.getElementById('overviewFinishedCount').textContent = lineCount;

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

    let summaryIndex = 0;
    cards.forEach(function(card) {
        const lines = isProductAddingForm
            ? [...card.querySelectorAll('.fg-variant-row')].map(row => ({
                product: row.querySelector('.fg-variant-name')?.value || '-',
                hsn: row.querySelector('.fg-variant-hsn')?.value || '-',
                qty: parseInt(row.querySelector('.fg-variant-qty')?.value, 10) || 0,
                cost: parseFloat(row.querySelector('.fg-variant-selling')?.value) || 0,
                gstRateField: row.querySelector('.fg-variant-gst-rate')
            }))
            : [{
                product: card.querySelector('.fg-input-product')?.value || '-',
                hsn: card.querySelector('.fg-input-hsn')?.value || '-',
                qty: parseInt(card.querySelector('.fg-input-qty')?.value, 10) || 0,
                cost: parseFloat(card.querySelector('.fg-input-cost')?.value) || 0,
                gstRateField: null
            }];

        lines.forEach(line => {
            const taxInfo = calculateItemTax(
                card,
                'fg',
                line.qty,
                line.cost,
                line.gstRateField ? line.gstRateField.value : undefined
            );
            totalFinishedBase += taxInfo.base;
            totalFinishedTax += taxInfo.tax;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${++summaryIndex}</td>
                <td class="fw-bold text-dark text-start">${escapeHtml(line.product)}</td>
                <td class="font-monospace">${escapeHtml(line.hsn)}</td>
                <td class="fw-bold">${line.qty}</td>
                <td>₹${line.cost.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td>${taxInfo.badge}</td>
                <td>${taxInfo.taxHtml}</td>
                <td class="table-success fw-bold text-success">₹${taxInfo.total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            `;
            tbody.appendChild(tr);
        });
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

    if (!isProductAddingForm) {
        document.querySelectorAll('#simplePurchaseItemsContainer .simple-purchase-card').forEach(function(card) {
            const qty = parseFloat(card.querySelector('.simple-input-qty')?.value) || 0;
            const price = parseFloat(card.querySelector('.simple-input-price')?.value) || 0;
            const taxInfo = calculateItemTax(card, 'simple', qty, price);
            grandBase += taxInfo.base;
            grandTax += taxInfo.tax;
        });
    } else {
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
            card.querySelectorAll('.fg-variant-row').forEach(row => {
                const qty = parseInt(row.querySelector('.fg-variant-qty')?.value, 10) || 0;
                const cost = parseFloat(row.querySelector('.fg-variant-selling')?.value) || 0;
                const rate = row.querySelector('.fg-variant-gst-rate')?.value ?? '';
                const taxInfo = calculateItemTax(card, 'fg', qty, cost, rate);
                grandBase += taxInfo.base;
                grandTax += taxInfo.tax;
            });
        });
    }

    const grandTotal = grandBase + grandTax;

    document.getElementById('summarySubtotal').textContent = '₹' + grandBase.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('summaryTaxes').textContent = '₹' + grandTax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('summaryTotalPrice').textContent = '₹' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ==========================================
// 4B. SIMPLIFIED PURCHASE BILLING (SINGLE TABLE)
// ==========================================
let simpleItemCounter = 0;

function addSimplePurchaseRow(initialData = null) {
    const container = document.getElementById('simplePurchaseItemsContainer');
    const placeholder = document.getElementById('noSimpleItemsPlaceholder');
    if (!container) return;
    if (placeholder) placeholder.style.display = 'none';

    simpleItemCounter++;
    const id = simpleItemCounter;
    const productType = initialData?.product_type || 'rolls';
    const itemName = initialData?.name || '';
    const qty = initialData?.qty !== undefined && initialData?.qty !== null ? initialData.qty : 1;
    const price = initialData?.price !== undefined && initialData?.price !== null ? initialData.price : 0;
    const hsn = initialData?.hsn || '';
    const globalTax = getGlobalTaxSettings();
    const taxMode = initialData?.tax_mode || globalTax.taxMode;
    const gstType = initialData?.gst_type || globalTax.gstType;
    const taxInputType = initialData?.tax_input_type || globalTax.taxInputType;
    const sgst = parseFloat(initialData?.sgst) || 0;
    const cgst = parseFloat(initialData?.cgst) || 0;
    const igst = parseFloat(initialData?.igst) || 0;

    const tr = document.createElement('tr');
    tr.className = 'simple-purchase-card';
    tr.id = 'simpleCard_' + id;
    tr.innerHTML = `
        <td class="text-center text-muted fw-semibold px-1 simple-row-index">${id}</td>
        <td class="px-1">
            <select class="form-select form-select-sm px-1 simple-input-type" name="purchase_items[${id}][product_type]" required onchange="syncSimplePurchaseTable()">
                <option value="rolls" ${productType === 'rolls' ? 'selected' : ''}>Fabric</option>
                <option value="garment_other" ${productType === 'garment_other' ? 'selected' : ''}>Other Garments Supply</option>
                <option value="finished_goods" ${productType === 'finished_goods' ? 'selected' : ''}>Finished Goods</option>
            </select>
        </td>
        <td class="px-1">
            <input type="text" class="form-control form-control-sm px-2 simple-input-name" name="purchase_items[${id}][name]" value="${escapeHtml(itemName)}" placeholder="Item name" required oninput="syncSimplePurchaseTable()">
        </td>
        <td class="px-1">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm px-1 font-monospace simple-input-hsn" name="purchase_items[${id}][hsn]" data-hsn-autocomplete data-hsn-type="HSN" autocomplete="off" value="${escapeHtml(hsn)}" placeholder="HSN" ${getHsnInputAttributes(hsn)} oninput="onSimpleHsnInput(${id})" onchange="onSimpleHsnChange(${id})">
                <button type="button" class="btn btn-outline-primary px-1" title="Add New HSN" onclick="promptAddHsn(${id}, 'simple')">+</button>
            </div>
        </td>
        <td class="px-1">
            <input type="number" step="1" min="1" class="form-control form-control-sm px-1 text-end simple-input-qty" name="purchase_items[${id}][qty]" value="${qty}" required oninput="syncSimplePurchaseTable()">
        </td>
        <td class="px-1">
            <input type="number" step="0.01" min="0" class="form-control form-control-sm px-1 text-end simple-input-price" name="purchase_items[${id}][price]" value="${price}" required oninput="syncSimplePurchaseTable()">
        </td>
        <td class="px-1">
            <span class="simple-row-gst-rate text-center d-block" aria-label="GST rate">0.00%</span>
            <div class="d-none">
            <input type="hidden" class="simple-input-tax-mode" name="purchase_items[${id}][tax_mode]" value="${escapeHtml(taxMode)}">
            <input type="hidden" class="simple-input-gst" name="purchase_items[${id}][gst]" value="${escapeHtml(gstType)}">
            <input type="hidden" class="simple-input-tax-type" name="purchase_items[${id}][tax_input_type]" value="${escapeHtml(taxInputType)}">
            <span class="badge bg-light text-dark border ${gstType === 'Exempted' ? '' : 'd-none'}" id="simpleExemptBadge_${id}">Exempted</span>
            <div class="d-flex gap-1" id="simpleGstDynamic_${id}" style="display: none;">
                <div class="flex-fill" id="simpleSgstCol_${id}" style="display: none;">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm px-1 simple-input-sgst" name="purchase_items[${id}][sgst]" value="${sgst}" placeholder="SGST" title="SGST" oninput="syncSimplePurchaseTable()">
                </div>
                <div class="flex-fill" id="simpleCgstCol_${id}" style="display: none;">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm px-1 simple-input-cgst" name="purchase_items[${id}][cgst]" value="${cgst}" placeholder="CGST" title="CGST" oninput="syncSimplePurchaseTable()">
                </div>
                <div class="flex-fill" id="simpleIgstCol_${id}" style="display: none;">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm px-1 simple-input-igst" name="purchase_items[${id}][igst]" value="${igst}" placeholder="IGST" title="IGST" oninput="syncSimplePurchaseTable()">
                </div>
            </div>
            </div>
        </td>
        <td class="text-end fw-bold text-success bg-success bg-opacity-10 simple-row-total">₹0.00</td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete Row" onclick="removeSimplePurchaseRow(${id})">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    container.appendChild(tr);
    onSimpleTaxInputTypeChange(id);
    onSimpleGstTypeChange(id);
}

function removeSimplePurchaseRow(id) {
    const card = document.getElementById('simpleCard_' + id);
    if (card) card.remove();
    const container = document.getElementById('simplePurchaseItemsContainer');
    const placeholder = document.getElementById('noSimpleItemsPlaceholder');
    if (container && placeholder && container.querySelectorAll('.simple-purchase-card').length === 0) {
        placeholder.style.display = 'block';
    }
    syncSimplePurchaseTable();
}

function onSimpleTaxInputTypeChange(id) {
    syncSimplePurchaseTable();
}

function onSimpleGstTypeChange(id) {
    const card = document.getElementById('simpleCard_' + id);
    if (!card) return;
    const gstType = card.querySelector('.simple-input-gst')?.value || 'Exempted';
    const typeCol = document.getElementById('simpleTaxInputTypeCol_' + id);
    const dynRow = document.getElementById('simpleGstDynamic_' + id);
    const sgstCol = document.getElementById('simpleSgstCol_' + id);
    const cgstCol = document.getElementById('simpleCgstCol_' + id);
    const igstCol = document.getElementById('simpleIgstCol_' + id);

    const exemptBadge = document.getElementById('simpleExemptBadge_' + id);
    if (gstType === 'Exempted') {
        if (typeCol) typeCol.style.display = 'none';
        if (dynRow) dynRow.style.display = 'none';
        if (exemptBadge) exemptBadge.classList.remove('d-none');
    } else {
        if (typeCol) typeCol.style.display = 'block';
        if (dynRow) dynRow.style.display = 'flex';
        if (exemptBadge) exemptBadge.classList.add('d-none');
        if (sgstCol) sgstCol.style.display = (gstType === 'Both (CGST and SGST)' || gstType === 'SGST') ? 'block' : 'none';
        if (cgstCol) cgstCol.style.display = (gstType === 'Both (CGST and SGST)' || gstType === 'CGST') ? 'block' : 'none';
        if (igstCol) igstCol.style.display = (gstType === 'IGST') ? 'block' : 'none';
    }
    syncSimplePurchaseTable();
}

function onSimpleHsnChange(id) {
    const card = document.getElementById('simpleCard_' + id);
    if (!card) return;
    const hsnInput = card.querySelector('.simple-input-hsn');
    if (!hsnInput) return;
    resolveHsnInputMetadata(hsnInput);
    updateHsnRateNote(hsnInput);
    applyHsnRateToGstInputs(card, 'simple');
    onSimpleGstTypeChange(id);
}

function onSimpleHsnInput(id) {
    const card = document.getElementById('simpleCard_' + id);
    if (!card) return;
    const hsnInput = card.querySelector('.simple-input-hsn');
    if (!hsnInput) return;
    resolveHsnInputMetadata(hsnInput);
    updateHsnRateNote(hsnInput);
    const rateText = hsnInput.dataset.hsnGstRate;
    if (rateText !== '' && rateText !== undefined && rateText !== null) {
        applyHsnRateToGstInputs(card, 'simple');
        onSimpleGstTypeChange(id);
    }
}

function syncSimplePurchaseTable() {
    const countBadge = document.getElementById('simpleItemsCountBadge');
    const rows = document.querySelectorAll('#simplePurchaseItemsContainer .simple-purchase-card');

    if (countBadge) {
        countBadge.textContent = rows.length + (rows.length === 1 ? ' Item' : ' Items');
    }

    rows.forEach((row, idx) => {
        const idxCell = row.querySelector('.simple-row-index');
        if (idxCell) idxCell.textContent = String(idx + 1);

        const qty = parseFloat(row.querySelector('.simple-input-qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.simple-input-price')?.value) || 0;
        const taxInfo = calculateItemTax(row, 'simple', qty, price);
        const gstType = row.querySelector('.simple-input-gst')?.value || 'Exempted';
        const taxInputType = row.querySelector('.simple-input-tax-type')?.value || 'Percentage';
        const gstRateCell = row.querySelector('.simple-row-gst-rate');
        if (gstRateCell) {
            if (gstType === 'Exempted') {
                gstRateCell.textContent = '0.00%';
            } else if (taxInputType !== 'Percentage') {
                gstRateCell.textContent = '—';
            } else {
                const gstRate = ['sgst', 'cgst', 'igst'].reduce((rate, component) => {
                    return rate + (parseFloat(row.querySelector(`.simple-input-${component}`)?.value) || 0);
                }, 0);
                gstRateCell.textContent = gstRate.toFixed(2) + '%';
            }
        }

        const totalCell = row.querySelector('.simple-row-total');
        if (totalCell) totalCell.textContent = '₹' + taxInfo.total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });

    recalcOverallSummary();
}

// ==========================================
// 5. LOAD EXISTING ITEMS ON EDIT
// ==========================================
function loadInitialData() {
    if (!isEditMode) return;

    if (!isProductAddingForm) {
        const combined = [];
        if (Array.isArray(existingRolls)) {
            existingRolls.forEach(r => combined.push({
                product_type: 'rolls',
                name: r.fabric_name || r.item_name || '',
                qty: parseFloat(r.qty) || 1,
                price: parseFloat(r.unit_price) || 0,
                hsn: r.hsn_code || '',
                tax_mode: r.tax_mode || 'Exclude GST',
                gst_type: r.gst_type || 'Exempted',
                tax_input_type: r.tax_input_type || 'Percentage',
                sgst: parseFloat(r.sgst) || 0,
                cgst: parseFloat(r.cgst) || 0,
                igst: parseFloat(r.igst) || 0
            }));
        }
        if (Array.isArray(existingOther)) {
            existingOther.forEach(g => combined.push({
                product_type: 'garment_other',
                name: g.item_name || '',
                qty: parseFloat(g.qty) || 1,
                price: parseFloat(g.unit_price) || 0,
                hsn: g.hsn_code || '',
                tax_mode: g.tax_mode || 'Exclude GST',
                gst_type: g.gst_type || 'Exempted',
                tax_input_type: g.tax_input_type || 'Percentage',
                sgst: parseFloat(g.sgst) || 0,
                cgst: parseFloat(g.cgst) || 0,
                igst: parseFloat(g.igst) || 0
            }));
        }
        if (Array.isArray(existingFinished)) {
            existingFinished.forEach(f => combined.push({
                product_type: 'finished_goods',
                name: f.item_name || '',
                qty: parseFloat(f.qty) || 1,
                price: parseFloat(f.unit_price) || 0,
                hsn: f.hsn_code || '',
                tax_mode: f.tax_mode || 'Exclude GST',
                gst_type: f.gst_type || 'Exempted',
                tax_input_type: f.tax_input_type || 'Percentage',
                sgst: parseFloat(f.sgst) || 0,
                cgst: parseFloat(f.cgst) || 0,
                igst: parseFloat(f.igst) || 0
            }));
        }
        if (combined.length > 0) {
            combined.forEach(item => addSimplePurchaseRow(item));
        } else {
            addSimplePurchaseRow();
        }
        return;
    }

    if (Array.isArray(existingRolls) && existingRolls.length > 0) {
        const container = document.getElementById('rollsContainer');
        container.innerHTML = '';
        rollCounter = 0;

        existingRolls.forEach(function(r) {
            addRollRow();
            const row = document.getElementById('rollCard_' + rollCounter);
            if (!row) return;
            const existingPhoto = r.photo_path || '';
            const existingPhotoInput = row.querySelector('input[name$="[existing_photo]"]');
            if (existingPhotoInput) existingPhotoInput.value = existingPhoto;
            if (existingPhoto) {
                const photoUrl = (existingPhoto.startsWith('http://') || existingPhoto.startsWith('https://'))
                    ? existingPhoto
                    : ('<?= \App\Core\Controller::url('/') ?>' + existingPhoto.replace(/^\/+/, ''));
                const existingPhotoName = existingPhoto.split('/').pop() || 'Roll Photo';
                row.dataset.photoUrl = photoUrl;
                row.dataset.photoName = existingPhotoName;
                renderRollPhotoPreviewChip(rollCounter, photoUrl, existingPhotoName);
            }
            if (row.querySelector('.roll-input-code')) row.querySelector('.roll-input-code').value = r.item_code || r.item_name || '';
            if (row.querySelector('.roll-input-color')) row.querySelector('.roll-input-color').value = r.color || '';
            if (row.querySelector('.roll-input-pattern')) row.querySelector('.roll-input-pattern').value = r.pattern || '';
            if (row.querySelector('.roll-input-rq')) row.querySelector('.roll-input-rq').value = parseInt(r.rq_qty || r.qty) || 1;
            if (row.querySelector('.roll-input-weight')) row.querySelector('.roll-input-weight').value = parseFloat(r.net_weight) || '';
            if (row.querySelector('.roll-input-price')) row.querySelector('.roll-input-price').value = parseFloat(r.unit_price) || '';
            const hsnInput = row.querySelector('.roll-input-hsn');
            if (hsnInput) {
                hsnInput.value = r.hsn_code || '';
                setHsnInputMetadata(hsnInput, hsnInput.value);
            }
            if (row.querySelector('.roll-input-tax-mode')) row.querySelector('.roll-input-tax-mode').value = r.tax_mode || 'Exclude GST';
            const gstType = r.gst_type || 'Exempted';
            const gstRate = parseFloat(r.gst_rate) || 0;
            const halfRate = (gstRate / 2).toFixed(2);
            if (row.querySelector('.roll-input-gst')) row.querySelector('.roll-input-gst').value = gstType;
            if (row.querySelector('.roll-input-sgst')) row.querySelector('.roll-input-sgst').value = gstType === 'Both (CGST and SGST)' ? halfRate : (gstType === 'SGST' ? gstRate : 0);
            if (row.querySelector('.roll-input-cgst')) row.querySelector('.roll-input-cgst').value = gstType === 'Both (CGST and SGST)' ? halfRate : (gstType === 'CGST' ? gstRate : 0);
            if (row.querySelector('.roll-input-igst')) row.querySelector('.roll-input-igst').value = gstType === 'IGST' ? gstRate : 0;
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
    if (isProductAddingForm) {
        if (isEditMode) {
            loadInitialData();
        }
        syncRollsTable();
        syncOtherTable();
        syncFinishedTable();
    } else {
        if (isEditMode) {
            loadInitialData();
        } else {
            addSimplePurchaseRow();
        }
        syncSimplePurchaseTable();
    }

    const tabFinishedBtn = document.getElementById('tabFinishedBtn');
    if (tabFinishedBtn && isProductAddingForm) {
        tabFinishedBtn.addEventListener('shown.bs.tab', function () {
            const container = document.getElementById('finishedGoodsContainer');
            if (container && container.querySelectorAll('.finished-card').length === 0) {
                addFinishedRow();
            }
        });
    }

    const wholeNumberQtySelector = '.fg-variant-qty, [data-quick-fill-field="quantity"], .fg-input-qty, .other-input-qty, .simple-input-qty';
    document.addEventListener('keydown', function (event) {
        if (!event.target.matches(wholeNumberQtySelector)) return;
        if (['.', ',', 'e', 'E', '+', '-'].includes(event.key)) {
            event.preventDefault();
        }
    });
    document.addEventListener('input', function (event) {
        if (!event.target.matches(wholeNumberQtySelector)) return;
        const cleaned = event.target.value.replace(/[^0-9]/g, '');
        if (event.target.value !== cleaned) {
            event.target.value = cleaned;
        }
    });

    const bulkLabelForm = document.getElementById('productAddingBulkLabelForm');
    const bulkStockButton = document.getElementById('productAddingBulkStockButton');
    const bulkStockForm = document.getElementById('productAddingBulkStockForm');
    const selectAllLinesButton = document.getElementById('productAddingSelectAllLines');
    if (bulkLabelForm && bulkStockButton && bulkStockForm) {
        const updateBulkStockButton = function () {
            const selectedItems = Array.from(bulkLabelForm.querySelectorAll('input[type="checkbox"][name="item_ids[]"]:checked'));
            const eligibleItems = selectedItems.filter(function (input) {
                return input.dataset.labelsComplete === '1' && input.dataset.inStock !== '1';
            });
            bulkStockButton.disabled = eligibleItems.length === 0;
            bulkStockButton.title = eligibleItems.length > 0
                ? `Add ${eligibleItems.length} label-complete selected line(s) to stock`
                : 'Select pending lines with complete label coverage';
        };
        if (selectAllLinesButton) {
            selectAllLinesButton.addEventListener('click', function () {
                bulkLabelForm.querySelectorAll('input[type="checkbox"][name="item_ids[]"]').forEach(function (input) {
                    if (input.dataset.inStock !== '1') {
                        input.checked = true;
                    }
                });
                updateBulkStockButton();
                const feedback = document.getElementById('productAddingBulkLabelFeedback');
                if (feedback) {
                    feedback.textContent = '';
                    feedback.classList.add('d-none');
                }
            });
        }
        bulkLabelForm.addEventListener('change', function (event) {
            if (event.target.matches('input[type="checkbox"][name="item_ids[]"]')) {
                updateBulkStockButton();
                const feedback = document.getElementById('productAddingBulkLabelFeedback');
                if (feedback) {
                    feedback.textContent = '';
                    feedback.classList.add('d-none');
                }
            }
        });
        bulkStockForm.addEventListener('submit', function (event) {
            bulkStockForm.querySelectorAll('input[name="item_ids[]"]').forEach(function (input) {
                input.remove();
            });
            const selectedItems = Array.from(bulkLabelForm.querySelectorAll('input[type="checkbox"][name="item_ids[]"]:checked'));
            const eligibleItems = selectedItems.filter(function (input) {
                return input.dataset.labelsComplete === '1' && input.dataset.inStock !== '1';
            });
            if (eligibleItems.length === 0) {
                event.preventDefault();
                const feedback = document.getElementById('productAddingBulkLabelFeedback');
                if (feedback) {
                    feedback.textContent = 'Select pending lines with complete label coverage before adding stock.';
                    feedback.classList.remove('d-none');
                }
                return;
            }
            eligibleItems.forEach(function (input) {
                const itemId = document.createElement('input');
                itemId.type = 'hidden';
                itemId.name = 'item_ids[]';
                itemId.value = input.value;
                bulkStockForm.appendChild(itemId);
            });
        });
        updateBulkStockButton();
    }

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
    const invoiceInput = form.querySelector('#billSupplierInvoiceNo');
    const supplierInput = form.querySelector('#supplierSelect');
    const invoiceStatus = form.querySelector('#billSupplierInvoiceStatus');
    let invoiceCheckTimer = null;
    let invoiceCheckRequest = 0;
    let invoiceSubmitPending = false;
    let allowSubmitAfterInvoiceCheck = false;
    let matchedPurchaseBill = null;
    const getCurrentProductAddingQty = function () {
        let total = 0;
        form.querySelectorAll('#rollsContainer .roll-card .roll-input-rq').forEach(el => {
            total += parseFloat(el.value) || 0;
        });
        form.querySelectorAll('#garmentOtherContainer .other-card .other-input-qty').forEach(el => {
            total += parseFloat(el.value) || 0;
        });
        form.querySelectorAll('#finishedGoodsContainer .finished-card .fg-variant-qty, #finishedGoodsContainer .finished-card .fg-input-qty').forEach(el => {
            total += parseFloat(el.value) || 0;
        });
        return total;
    };
    const updateProductAddingQtyMismatchAlert = function () {
        if (!isProductAddingForm || !matchedPurchaseBill || !invoiceStatus) return;
        const invQty = parseFloat(matchedPurchaseBill.invoice_qty) || 0;
        const remQty = parseFloat(matchedPurchaseBill.remaining_qty) || 0;
        const sourceLabel = matchedPurchaseBill.source_module === 'product_adding' ? 'Product Adding' : 'Purchase Billing';
        const currentQty = getCurrentProductAddingQty();
        if (currentQty > 0 && Math.abs(currentQty - remQty) > 0.0001) {
            invoiceStatus.textContent = `Qty Mismatch Alert: Product Adding qty (${currentQty}) does not match ${sourceLabel} invoice qty (${remQty}). Saving is still allowed.`;
            invoiceStatus.className = 'form-text small text-warning fw-semibold';
        } else {
            invoiceStatus.textContent = `Matched ${sourceLabel} Invoice #${matchedPurchaseBill.bill_no} (Qty: ${invQty}) — Supplier auto-filled.`;
            invoiceStatus.className = 'form-text small text-info fw-semibold';
        }
    };
    const clearInvoiceError = function () {
        matchedPurchaseBill = null;
        if (invoiceInput) {
            invoiceInput.setCustomValidity('');
            clearFieldError(invoiceInput);
        }
        if (invoiceStatus) {
            invoiceStatus.textContent = '';
            invoiceStatus.className = 'form-text small';
        }
    };
    const checkSupplierInvoiceNumber = async function () {
        if (!invoiceInput || !supplierInput || !invoiceCheckUrl) return false;
        const supplierId = supplierInput.value;
        const invoiceNo = invoiceInput.value.trim();
        if (!invoiceNo) {
            clearInvoiceError();
            return false;
        }

        const requestId = ++invoiceCheckRequest;
        if (invoiceStatus) {
            invoiceStatus.textContent = 'Checking invoice number...';
            invoiceStatus.className = 'form-text small text-muted';
        }
        const requestData = new FormData();
        requestData.append('csrf_token', form.querySelector('input[name="csrf_token"]')?.value || '');
        requestData.append('supplier_id', supplierId);
        requestData.append('supplier_invoice_no', invoiceNo);
        requestData.append('bill_id', form.querySelector('input[name="bill_id"]')?.value || '');
        requestData.append('concern_id', form.querySelector('[name="concern_id"]')?.value || '');
        requestData.append('product_adding_redirect', form.querySelector('[name="product_adding_redirect"]')?.value || '');

        try {
            const response = await fetch(invoiceCheckUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: requestData
            });
            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Invoice verification endpoint returned a non-JSON response.', response.status);
                if (requestId !== invoiceCheckRequest) return false;
                clearInvoiceError();
                if (invoiceStatus) {
                    invoiceStatus.textContent = 'Invoice pre-check is unavailable; duplicates will still be checked when saving.';
                    invoiceStatus.className = 'form-text small text-warning';
                }
                return false;
            }
            if (requestId !== invoiceCheckRequest) return false;
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to verify this invoice number.');
            }
            if (result.duplicate) {
                matchedPurchaseBill = null;
                const message = result.message || 'This supplier invoice number has already been recorded for the selected supplier.';
                invoiceInput.setCustomValidity(message);
                showFieldError(invoiceInput);
                if (invoiceStatus) {
                    invoiceStatus.textContent = '';
                    invoiceStatus.className = 'form-text small text-danger';
                    if (result.existing_invoice_edit_url) {
                        invoiceStatus.appendChild(document.createTextNode('An unstocked Purchase Billing invoice exists. '));
                        const editLink = document.createElement('a');
                        editLink.href = result.existing_invoice_edit_url;
                        editLink.className = 'btn btn-sm btn-outline-primary ms-1';
                        editLink.textContent = 'Load existing invoice to edit';
                        invoiceStatus.appendChild(editLink);
                    }
                }
                return true;
            }

            clearInvoiceError();
            if (result.matched_purchase_bill) {
                matchedPurchaseBill = result.matched_purchase_bill;
                if (!isEditMode && !supplierInput.value && matchedPurchaseBill.supplier_id) {
                    supplierInput.value = String(matchedPurchaseBill.supplier_id);
                    const selectedOpt = supplierInput.options[supplierInput.selectedIndex];
                    const searchInput = document.getElementById('supplierSearchInput');
                    if (searchInput && selectedOpt) {
                        searchInput.value = selectedOpt.textContent.trim();
                        searchInput.classList.remove('is-invalid');
                    }
                    supplierInput.setCustomValidity('');
                }
                updateProductAddingQtyMismatchAlert();
            } else {
                if (attachmentsInput && !hasExistingAttachments) {
                    attachmentsInput.required = true;
                }
                if (invoiceStatus) {
                    invoiceStatus.textContent = 'Invoice number is available.';
                    invoiceStatus.className = 'form-text small text-success';
                }
            }
            return false;
        } catch (error) {
            if (requestId !== invoiceCheckRequest) return false;
            const message = error.message || 'Unable to verify this invoice number. Try again.';
            invoiceInput.setCustomValidity(message);
            showFieldError(invoiceInput);
            if (invoiceStatus) invoiceStatus.textContent = '';
            return true;
        }
    };
    const scheduleSupplierInvoiceCheck = function () {
        clearTimeout(invoiceCheckTimer);
        invoiceCheckRequest++;
        clearInvoiceError();
        if (!invoiceInput?.value.trim()) return;
        invoiceCheckTimer = setTimeout(checkSupplierInvoiceNumber, 300);
    };
    if (invoiceInput && supplierInput) {
        invoiceInput.addEventListener('input', scheduleSupplierInvoiceCheck);
        invoiceInput.addEventListener('blur', function () {
            clearTimeout(invoiceCheckTimer);
            checkSupplierInvoiceNumber();
        });
        supplierInput.addEventListener('change', scheduleSupplierInvoiceCheck);
        if (isProductAddingForm) {
            const syncQtyAlertIfMatched = function (event) {
                if (event.target && event.target.matches('.roll-input-rq, .other-input-qty, .fg-variant-qty, .fg-input-qty')) {
                    updateProductAddingQtyMismatchAlert();
                }
            };
            form.addEventListener('input', syncQtyAlertIfMatched);
            form.addEventListener('change', syncQtyAlertIfMatched);
        }

        form.addEventListener('submit', function (event) {
            if (allowSubmitAfterInvoiceCheck) {
                allowSubmitAfterInvoiceCheck = false;
                return;
            }
            if (supplierInput && !supplierInput.value) {
                event.preventDefault();
                event.stopPropagation();
                supplierInput.setCustomValidity('Please choose a supplier.');
                showFieldError(supplierInput);
                const searchInput = document.getElementById('supplierSearchInput');
                if (searchInput) {
                    searchInput.classList.add('is-invalid');
                    searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    searchInput.focus();
                }
                form.classList.add('was-validated');
                return;
            } else if (supplierInput) {
                supplierInput.setCustomValidity('');
                document.getElementById('supplierSearchInput')?.classList.remove('is-invalid');
            }
            event.preventDefault();
            if (invoiceSubmitPending) return;
            invoiceSubmitPending = true;
            clearTimeout(invoiceCheckTimer);
            checkSupplierInvoiceNumber().then(function (isDuplicate) {
                if (isDuplicate) {
                    invoiceInput.focus();
                    return;
                }
                allowSubmitAfterInvoiceCheck = true;
                if (event.submitter) {
                    form.requestSubmit(event.submitter);
                } else {
                    form.requestSubmit();
                }
            }).finally(function () {
                invoiceSubmitPending = false;
            });
        }, true);
    }
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
        if (supplierInput && !supplierInput.value) {
            supplierInput.setCustomValidity('Please choose a supplier.');
        } else if (supplierInput) {
            supplierInput.setCustomValidity('');
            document.getElementById('supplierSearchInput')?.classList.remove('is-invalid');
        }
        validateAttachments();
        if (isProductAddingForm) {
            form.querySelectorAll('#finishedGoodsContainer .finished-card').forEach(function (card) {
                card.querySelectorAll('.fg-variant-row').forEach(row => {
                    updateProductAddingVariantRow(parseInt(card.id.replace('finishedCard_', ''), 10), parseInt(row.dataset.rowIndex, 10));
                });
            });
            if (matchedPurchaseBill && invoiceInput) {
                updateProductAddingQtyMismatchAlert();
            }
        }
        const invalidFields = Array.from(form.elements).filter(function (control) {
            return control.willValidate && !control.checkValidity();
        });
        const hasItem = isProductAddingForm
            ? form.querySelector('.roll-card, .other-card, .finished-card')
            : form.querySelector('.simple-purchase-card');
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
            if (invalidFields.length > 0 && itemsError) {
                const missingLabels = invalidFields
                    .map(getFieldLabel)
                    .filter(Boolean)
                    .filter((v, i, a) => a.indexOf(v) === i);
                if (missingLabels.length > 0) {
                    itemsError.textContent = 'Please check required field(s): ' + missingLabels.join(', ');
                    itemsError.classList.remove('d-none');
                }
            }
            const firstInvalid = invalidFields[0];
            if (firstInvalid) {
                const pane = firstInvalid.closest('.tab-pane');
                if (pane && pane.id === 'paneRolls') document.getElementById('tabRollsBtn')?.click();
                if (pane && pane.id === 'paneOther') document.getElementById('tabOtherBtn')?.click();
                if (pane && pane.id === 'paneFinished') document.getElementById('tabFinishedBtn')?.click();
                const focusTarget = (firstInvalid === supplierInput)
                    ? (document.getElementById('supplierSearchInput') || firstInvalid)
                    : firstInvalid;
                if (firstInvalid === supplierInput && focusTarget) {
                    focusTarget.classList.add('is-invalid');
                }
                setTimeout(() => {
                    focusTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    focusTarget.focus();
                }, 150);
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabFinishedBtn = document.getElementById('tabFinishedBtn');
    if (tabFinishedBtn && isProductAddingForm) {
        tabFinishedBtn.addEventListener('shown.bs.tab', function () {
            const container = document.getElementById('finishedGoodsContainer');
            if (container && container.querySelectorAll('.finished-card').length === 0) {
                addFinishedRow();
            }
        });
    }

    const wholeNumberQtySelector = '.fg-variant-qty, [data-quick-fill-field="quantity"], .fg-input-qty, .other-input-qty, .simple-input-qty';
    document.addEventListener('keydown', function (event) {
        if (!event.target.matches(wholeNumberQtySelector)) return;
        if (['.', ',', 'e', 'E', '+', '-'].includes(event.key)) {
            event.preventDefault();
        }
    });
    document.addEventListener('input', function (event) {
        if (!event.target.matches(wholeNumberQtySelector)) return;
        const cleaned = event.target.value.replace(/[^0-9]/g, '');
        if (event.target.value !== cleaned) {
            event.target.value = cleaned;
        }
    });

    const bulkLabelForm = document.getElementById('productAddingBulkLabelForm');
    const countModalElement = document.getElementById('productAddingLabelCountModal');
    const countRows = document.getElementById('productAddingLabelCountRows');
    const countError = document.getElementById('productAddingLabelCountError');
    const getCountModal = function () {
        if (!countModalElement || !window.bootstrap) return null;
        return bootstrap.Modal.getOrCreateInstance(countModalElement);
    };
    const previewFrame = document.getElementById('productAddingLabelPreviewFrame');
    const previewPanel = document.getElementById('productAddingLabelPreviewPanel');
    const bulkLabelFeedback = document.getElementById('productAddingBulkLabelFeedback');
    const bulkStockButton = document.getElementById('productAddingBulkStockButton');
    if (!bulkLabelForm || !countModalElement || !countRows || !previewFrame || !previewPanel) return;

    let pendingLabelForm = null;

    const escapeHtml = function (value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[character];
        });
    };

    const brandInput = document.getElementById('productAddingBrandInput');
    const brandEditIdInput = document.getElementById('productAddingBrandEditId');
    const brandSaveBtn = document.getElementById('productAddingBrandSaveBtn');
    const brandCancelEditBtn = document.getElementById('productAddingBrandCancelEditBtn');
    const brandListEl = document.getElementById('productAddingBrandList');
    const brandFeedbackEl = document.getElementById('productAddingBrandFeedback');
    const brandCsrfToken = <?= json_encode($csrfToken ?? '') ?>;
    const brandListUrl = <?= json_encode($brandListUrl ?? \App\Core\Controller::url('/m/product_adding/brands')) ?>;
    const brandStoreUrl = <?= json_encode($brandStoreUrl ?? \App\Core\Controller::url('/m/product_adding/brands/store')) ?>;
    const brandUpdateUrl = <?= json_encode($brandUpdateUrl ?? \App\Core\Controller::url('/m/product_adding/brands/update')) ?>;
    const brandDeleteUrl = <?= json_encode($brandDeleteUrl ?? \App\Core\Controller::url('/m/product_adding/brands/delete')) ?>;
    let cachedBrands = <?= json_encode($brands ?? []) ?>;

    const showBrandFeedback = function (msg, isError = false) {
        if (!brandFeedbackEl) return;
        if (!msg) {
            brandFeedbackEl.className = 'small mt-1 d-none';
            brandFeedbackEl.textContent = '';
            return;
        }
        brandFeedbackEl.className = `small mt-1 ${isError ? 'text-danger' : 'text-success'}`;
        brandFeedbackEl.textContent = msg;
    };

    const resetBrandEditMode = function () {
        if (brandEditIdInput) brandEditIdInput.value = '';
        if (brandSaveBtn) brandSaveBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Save';
        if (brandCancelEditBtn) brandCancelEditBtn.classList.add('d-none');
    };

    const renderBrandList = function (filterText = '') {
        if (!brandListEl) return;
        const q = (filterText || '').trim().toLowerCase();
        const filtered = (Array.isArray(cachedBrands) ? cachedBrands : []).filter(function (b) {
            return !q || String(b.name || '').toLowerCase().includes(q);
        });
        if (!filtered.length) {
            brandListEl.innerHTML = '<span class="small text-muted">No saved brands match. Type a name and click Save or Generate labels.</span>';
            return;
        }
        brandListEl.innerHTML = filtered.map(function (b) {
            const bName = escapeHtml(b.name || '');
            return `
                <div class="badge bg-white text-dark border d-inline-flex align-items-center gap-1 px-2 py-1">
                    <button type="button" class="btn btn-link btn-sm p-0 text-dark text-decoration-none fw-semibold brand-select-btn" data-brand-name="${bName}" style="font-size: 0.78rem;">
                        ${bName}
                    </button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-primary brand-edit-btn ms-1" data-brand-id="${b.id}" data-brand-name="${bName}" title="Edit Brand" style="font-size: 0.75rem;">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger brand-delete-btn" data-brand-id="${b.id}" data-brand-name="${bName}" title="Delete Brand" style="font-size: 0.75rem;">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;
        }).join('');
    };

    const fetchBrandsDynamic = function (query = '') {
        const url = new URL(brandListUrl, window.location.origin);
        if (query) url.searchParams.set('q', query);
        fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (data && data.success && Array.isArray(data.brands)) {
                    if (!query) cachedBrands = data.brands;
                    renderBrandList(query ? '' : (brandInput ? brandInput.value : ''));
                    if (query && brandListEl) {
                        const tmp = cachedBrands;
                        cachedBrands = data.brands;
                        renderBrandList('');
                        cachedBrands = tmp;
                    }
                }
            })
            .catch(() => {});
    };

    if (brandInput) {
        let brandSearchTimer = null;
        brandInput.addEventListener('input', function () {
            showBrandFeedback('');
            renderBrandList(brandInput.value);
            clearTimeout(brandSearchTimer);
            brandSearchTimer = setTimeout(function () {
                fetchBrandsDynamic(brandInput.value.trim());
            }, 200);
        });
    }

    if (brandCancelEditBtn) {
        brandCancelEditBtn.addEventListener('click', function () {
            resetBrandEditMode();
            if (brandInput) brandInput.value = '';
            renderBrandList('');
            showBrandFeedback('');
        });
    }

    if (brandSaveBtn && brandInput) {
        brandSaveBtn.addEventListener('click', function () {
            const name = brandInput.value.trim();
            const editId = brandEditIdInput ? brandEditIdInput.value.trim() : '';
            if (!name) {
                showBrandFeedback('Please enter a brand name.', true);
                brandInput.focus();
                return;
            }
            const fd = new FormData();
            fd.append('csrf_token', brandCsrfToken);
            fd.append('name', name);
            if (editId) fd.append('id', editId);
            const targetUrl = editId ? brandUpdateUrl : brandStoreUrl;
            brandSaveBtn.disabled = true;
            fetch(targetUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) {
                    showBrandFeedback((data && data.message) || 'Could not save brand.', true);
                    return;
                }
                if (Array.isArray(data.brands)) cachedBrands = data.brands;
                showBrandFeedback(editId ? 'Brand updated.' : 'Brand saved.');
                resetBrandEditMode();
                renderBrandList('');
            })
            .catch(() => showBrandFeedback('Error saving brand.', true))
            .finally(() => { brandSaveBtn.disabled = false; });
        });
    }

    if (brandListEl) {
        brandListEl.addEventListener('click', function (e) {
            const selectBtn = e.target.closest('.brand-select-btn');
            if (selectBtn && brandInput) {
                brandInput.value = selectBtn.dataset.brandName || '';
                resetBrandEditMode();
                showBrandFeedback('');
                return;
            }
            const editBtn = e.target.closest('.brand-edit-btn');
            if (editBtn && brandInput && brandEditIdInput) {
                brandEditIdInput.value = editBtn.dataset.brandId || '';
                brandInput.value = editBtn.dataset.brandName || '';
                brandInput.focus();
                if (brandSaveBtn) brandSaveBtn.innerHTML = '<i class="bi bi-check2"></i> Update';
                if (brandCancelEditBtn) brandCancelEditBtn.classList.remove('d-none');
                showBrandFeedback('');
                return;
            }
            const deleteBtn = e.target.closest('.brand-delete-btn');
            if (deleteBtn) {
                const delId = deleteBtn.dataset.brandId || '';
                if (!delId) return;
                const fd = new FormData();
                fd.append('csrf_token', brandCsrfToken);
                fd.append('id', delId);
                fetch(brandDeleteUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success) {
                        if (Array.isArray(data.brands)) cachedBrands = data.brands;
                        if (brandEditIdInput && brandEditIdInput.value === delId) {
                            resetBrandEditMode();
                            if (brandInput) brandInput.value = '';
                        }
                        renderBrandList(brandInput ? brandInput.value : '');
                        showBrandFeedback('Brand deleted.');
                    } else {
                        showBrandFeedback((data && data.message) || 'Could not delete brand.', true);
                    }
                })
                .catch(() => showBrandFeedback('Error deleting brand.', true));
            }
        });
    }

    const openLabelCountModal = function (targetForm, items) {
        pendingLabelForm = targetForm;
        countError.textContent = '';
        showBrandFeedback('');
        resetBrandEditMode();
        renderBrandList(brandInput ? brandInput.value : '');
        fetchBrandsDynamic('');
        countRows.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Selected line</th><th class="text-end">Copies</th></tr></thead>
                    <tbody>
                        ${items.map(function (item) {
                            return `
                                <tr>
                                    <td>
                                        <div class="fw-semibold">${escapeHtml(item.name)}</div>
                                        <div class="small text-muted">${escapeHtml(item.section)} · print count / stock quantity ${escapeHtml(item.defaultCount)}</div>
                                    </td>
                                    <td style="width: 120px">
                                        <input type="number" class="form-control form-control-sm text-end"
                                            data-label-count-item-id="${item.id}"
                                            min="1" max="500" step="1" required
                                            aria-label="Label count for ${escapeHtml(item.name)}"
                                            value="${item.defaultCount}">
                                    </td>
                                </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>`;
        const modal = getCountModal();
        if (modal) modal.show();
    };

    const selectedLineInputs = function () {
        return Array.from(bulkLabelForm.querySelectorAll('input[type="checkbox"][name="item_ids[]"]:checked'));
    };

    const itemDataFromCheckbox = function (input) {
        return {
            id: input.value,
            name: input.dataset.itemName || 'Purchase item',
            section: input.dataset.itemSection || 'Purchase item',
            defaultCount: input.dataset.defaultLabelCount || '1'
        };
    };

    document.querySelectorAll('[data-label-form-id]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetForm = document.getElementById(button.dataset.labelFormId);
            if (!targetForm) return;
            openLabelCountModal(targetForm, [{
                id: button.dataset.labelItemId,
                name: button.dataset.labelItemName || 'Purchase item',
                section: button.dataset.labelItemSection || 'Purchase item',
                defaultCount: button.dataset.defaultLabelCount || '1'
            }]);
        });
    });

    const bulkLabelButton = document.getElementById('productAddingBulkLabelButton');
    if (bulkLabelButton) {
        bulkLabelButton.addEventListener('click', function () {
            const items = selectedLineInputs().map(itemDataFromCheckbox);
            if (!items.length) {
                bulkLabelFeedback.textContent = 'Select at least one bill line to print labels.';
                bulkLabelFeedback.classList.remove('d-none');
                return;
            }
            bulkLabelFeedback.textContent = '';
            bulkLabelFeedback.classList.add('d-none');
            openLabelCountModal(bulkLabelForm, items);
        });
    }

    if (bulkLabelFeedback) {
        bulkLabelForm.addEventListener('change', function (event) {
            if (event.target.matches('input[name="item_ids[]"]')) {
                bulkLabelFeedback.textContent = '';
                bulkLabelFeedback.classList.add('d-none');
            }
        });
    }

    const confirmCountsButton = document.getElementById('confirmProductAddingLabelCounts');
    if (confirmCountsButton) {
        confirmCountsButton.addEventListener('click', function () {
            if (!pendingLabelForm) return;
            const inputs = Array.from(countRows.querySelectorAll('[data-label-count-item-id]'));
            let totalCopies = 0;
            for (const input of inputs) {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    return;
                }
                totalCopies += Number(input.value);
            }
            if (totalCopies > 2000) {
                countError.textContent = 'A single print is limited to 2,000 labels. Reduce the selected line counts.';
                return;
            }

            pendingLabelForm.querySelectorAll('[data-generated-label-count]').forEach(function (input) {
                input.remove();
            });
            pendingLabelForm.querySelectorAll('[data-generated-label-item-id]').forEach(function (input) {
                input.remove();
            });
            pendingLabelForm.querySelectorAll('[data-generated-label-brand]').forEach(function (input) {
                input.remove();
            });
            if (brandInput && brandInput.value.trim() !== '') {
                const hiddenBrand = document.createElement('input');
                hiddenBrand.type = 'hidden';
                hiddenBrand.name = 'brand_name';
                hiddenBrand.value = brandInput.value.trim();
                hiddenBrand.dataset.generatedLabelBrand = '1';
                pendingLabelForm.appendChild(hiddenBrand);
            }
            inputs.forEach(function (input) {
                const hiddenItemId = document.createElement('input');
                hiddenItemId.type = 'hidden';
                hiddenItemId.name = 'item_ids[]';
                hiddenItemId.value = input.dataset.labelCountItemId;
                hiddenItemId.dataset.generatedLabelItemId = '1';
                pendingLabelForm.appendChild(hiddenItemId);

                const hiddenCount = document.createElement('input');
                hiddenCount.type = 'hidden';
                hiddenCount.name = `label_counts[${input.dataset.labelCountItemId}]`;
                hiddenCount.value = input.value;
                hiddenCount.dataset.generatedLabelCount = '1';
                pendingLabelForm.appendChild(hiddenCount);
            });

            const modal = getCountModal();
            if (modal) modal.hide();
            pendingLabelForm.submit();
            pendingLabelForm = null;
        });
    }

    const applyGeneratedLabelCounts = function (event) {
        const itemCounts = event?.data?.itemCounts;
        if (!itemCounts || typeof itemCounts !== 'object') return;

        Object.keys(itemCounts).forEach(function (itemId) {
            const checkbox = Array.from(bulkLabelForm.querySelectorAll('input[type="checkbox"][name="item_ids[]"]'))
                .find(function (input) { return input.value === itemId; });
            if (!checkbox) return;

            const generatedCount = Number(event.data.itemCounts[itemId]) || 0;
            checkbox.dataset.generatedLabelCount = String(generatedCount);
            checkbox.dataset.labelsComplete = generatedCount > 0 ? '1' : '0';
            if (generatedCount > 0 && checkbox.dataset.inStock !== '1') {
                checkbox.checked = true;
            }

            const progress = document.getElementById(`productAddingLabelProgress${itemId}`);
            if (progress) {
                const stockText = checkbox.dataset.inStock === '1'
                    ? `Stocked ${checkbox.dataset.stockQty || '—'}`
                    : `Stock qty ${checkbox.dataset.purchaseQty || '—'}`;
                progress.textContent = generatedCount > 0 ? `${generatedCount} labels · ${stockText}` : 'No labels printed';
                progress.classList.toggle('text-bg-success', generatedCount > 0);
                progress.classList.toggle('text-bg-warning', generatedCount <= 0);
            }

            const stockButton = document.querySelector(`[data-product-adding-stock-button="${itemId}"]`);
            if (stockButton && checkbox.dataset.inStock !== '1' && generatedCount > 0 && stockButton.disabled) {
                stockButton.type = 'submit';
                stockButton.setAttribute('form', stockButton.dataset.stockFormId);
                stockButton.disabled = false;
                stockButton.classList.remove('btn-outline-secondary');
                stockButton.classList.add('btn-success');
                stockButton.title = `Add ${checkbox.dataset.purchaseQty || 'all'} units to stock`;
                stockButton.innerHTML = '<i class="bi bi-box-arrow-in-down me-1"></i>Add stock';
            }
        });

        if (bulkStockButton) {
            const selectedItems = selectedLineInputs();
            const eligibleItems = selectedItems.filter(function (input) {
                return input.dataset.labelsComplete === '1' && input.dataset.inStock !== '1';
            });
            bulkStockButton.disabled = eligibleItems.length === 0;
            bulkStockButton.title = eligibleItems.length > 0
                ? `Add ${eligibleItems.length} label-complete selected line(s) to stock`
                : 'Select pending lines with complete label coverage';
        }
    };

    previewFrame.addEventListener('load', function () {
        const previewDocument = previewFrame.contentDocument;
        if (!previewDocument || !previewDocument.getElementById('labelGrid')) return;
        previewPanel.classList.remove('d-none');
        if (previewFrame.contentWindow && previewFrame.contentWindow.__productAddingGeneratedItemCounts) {
            applyGeneratedLabelCounts({ data: { itemCounts: previewFrame.contentWindow.__productAddingGeneratedItemCounts } });
        }
        const contentHeight = Math.max(
            previewDocument.documentElement.scrollHeight,
            previewDocument.body ? previewDocument.body.scrollHeight : 0
        );
        previewFrame.style.height = `${Math.max(640, contentHeight + 8)}px`;
        previewPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || event.source !== previewFrame.contentWindow || !event.data) return;
        if (event.data.type === 'product-adding-label-error') {
            if (bulkLabelFeedback) {
                bulkLabelFeedback.textContent = event.data.message || 'Could not generate labels.';
                bulkLabelFeedback.classList.remove('d-none');
            }
            previewPanel.classList.remove('d-none');
            previewFrame.style.height = '140px';
            previewPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        if (
            event.data.type !== 'product-adding-labels-generated' ||
            !event.data.itemCounts ||
            typeof event.data.itemCounts !== 'object'
        ) return;

        previewPanel.classList.remove('d-none');
        applyGeneratedLabelCounts(event);
    });
});
</script>
