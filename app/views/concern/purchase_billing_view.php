<?php
if (!function_exists('amountToWordsINR')) {
    function amountToWordsINR($amount) {
        $amount = (float)$amount;
        $num = (int)floor($amount);
        $fraction = (int)round(($amount - $num) * 100);

        $words = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
            30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
            80 => 'Eighty', 90 => 'Ninety'
        ];

        $convertTwoDigits = function($n) use ($words) {
            if ($n <= 20) return $words[$n];
            $t = (int)floor($n / 10) * 10;
            $u = $n % 10;
            return trim($words[$t] . ' ' . $words[$u]);
        };

        $convertThreeDigits = function($n) use ($words, $convertTwoDigits) {
            $h = (int)floor($n / 100);
            $r = $n % 100;
            $res = '';
            if ($h > 0) {
                $res .= $words[$h] . ' Hundred';
                if ($r > 0) $res .= ' ';
            }
            if ($r > 0) {
                $res .= $convertTwoDigits($r);
            }
            return trim($res);
        };

        if ($num === 0) {
            $result = 'Zero';
        } else {
            $parts = [];
            $crore = (int)floor($num / 10000000);
            $rem = $num % 10000000;
            if ($crore > 0) {
                $parts[] = $convertThreeDigits($crore) . ' Crore';
            }
            $lakh = (int)floor($rem / 100000);
            $rem = $rem % 100000;
            if ($lakh > 0) {
                $parts[] = $convertTwoDigits($lakh) . ' Lakh';
            }
            $thousand = (int)floor($rem / 1000);
            $rem = $rem % 1000;
            if ($thousand > 0) {
                $parts[] = $convertTwoDigits($thousand) . ' Thousand';
            }
            if ($rem > 0) {
                $parts[] = $convertThreeDigits($rem);
            }
            $result = implode(' ', $parts);
        }

        $out = 'INR ' . trim($result);
        if ($fraction > 0) {
            $out .= ' and ' . $convertTwoDigits($fraction) . ' Paise';
        }
        $out .= ' Only';
        return $out;
    }
}

$billAttachments = json_decode((string)($bill['attachment_paths'] ?? ''), true);
if (!is_array($billAttachments)) {
    $billAttachments = [];
}
$billAttachments = array_values(array_filter($billAttachments, static function ($path): bool {
    return is_string($path) && trim($path) !== '';
}));
if (empty($billAttachments) && !empty($bill['attachment_path'])) {
    $billAttachments[] = (string)$bill['attachment_path'];
}
?>
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 d-print-none">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-dark font-monospace px-2 py-1"><?= htmlspecialchars($bill['bill_no']) ?></span>
                <span class="badge bg-success-subtle text-success border px-2 py-1"><?= ucfirst($bill['payment_status']) ?></span>
                <span class="badge bg-info-subtle text-info border px-2 py-1"><?= ucfirst($bill['inward_status']) ?> Inward</span>
            </div>
            <h5 class="fw-bold mt-1 mb-0 text-dark">Production Purchase Details</h5>
            <p class="text-secondary small mb-0">Recorded on <?= date('d M Y', strtotime($bill['bill_date'])) ?> &bull; Supplier Invoice: <strong><?= htmlspecialchars($bill['supplier_invoice_no'] ?: 'N/A') ?></strong></p>
        </div>
        <div class="d-flex gap-2 flex-wrap d-print-none">
            <button type="button" class="btn btn-outline-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-1" onclick="triggerInvoicePrint()">
                <i class="bi bi-printer me-1"></i> Print Invoice
            </button>
            <?php if (\App\Core\Auth::can('purchase_billing', 'export')): ?>
            <a href="<?= \App\Core\Controller::url('/m/purchase_billing/download_items?id=' . $bill['id']) ?>" class="btn btn-success btn-sm px-3 shadow-sm d-flex align-items-center gap-1 text-white" style="background:#10b981; border-color:#10b981;">
                <i class="bi bi-download me-1"></i> Download Purchase Items
            </a>
            <?php endif; ?>
            <a href="<?= \App\Core\Controller::url(($bill['source_module'] ?? 'purchase_billing') === 'product_adding' ? '/m/product_adding' : '/m/purchase_billing') ?>" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Bills
            </a>
        </div>
    </div>

    <!-- View Mode Switcher -->
    <ul class="nav nav-pills p-1 bg-light border rounded-3 gap-1 d-inline-flex mb-3 d-print-none" id="viewModeTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-3 py-2 fw-semibold d-flex align-items-center gap-2" id="tabInteractiveBtn" data-bs-toggle="pill" data-bs-target="#paneInteractive" type="button" role="tab">
                <i class="bi bi-layout-text-window-reverse"></i> Interactive Breakdown
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 fw-semibold d-flex align-items-center gap-2" id="tabInvoicePrintBtn" data-bs-toggle="pill" data-bs-target="#paneInvoicePrint" type="button" role="tab">
                <i class="bi bi-receipt-cutoff"></i> Tax Invoice Print Format
            </button>
        </li>
    </ul>

    <div class="tab-content" id="viewModeTabContent">
        <!-- PANE 1: INTERACTIVE BREAKDOWN -->
        <div class="tab-pane fade show active" id="paneInteractive" role="tabpanel">

    <!-- Overview Cards -->
    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-primary">
                <span class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.04em;">Supplier Details</span>
                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($bill['supplier_name']) ?></h6>
                <?php if (!empty($bill['supplier_company'])): ?>
                    <div class="small text-secondary"><?= htmlspecialchars($bill['supplier_company']) ?></div>
                <?php endif; ?>
                <div class="small text-muted mt-1">
                    <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($bill['supplier_phone'] ?: '-') ?> &bull;
                    <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($bill['supplier_district'] ?: '-') ?>, <?= htmlspecialchars($bill['supplier_state'] ?: 'Tamil Nadu') ?><br>
                    <span class="font-monospace">GSTIN: <?= htmlspecialchars($bill['supplier_gstin'] ?: 'N/A') ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-info">
                <span class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.04em;">Purchase & Payment Info</span>
                <?php if (!empty($bill['fabric_description'])): ?>
                    <div class="fw-semibold text-dark small mb-1"><?= htmlspecialchars($bill['fabric_description']) ?></div>
                <?php endif; ?>
                <div class="small text-muted">
                    <strong>Date:</strong> <?= date('d-m-Y', strtotime($bill['bill_date'])) ?> &bull;
                    <strong>Inv #:</strong> <?= htmlspecialchars($bill['supplier_invoice_no'] ?: '-') ?><br>
                    <strong>Method:</strong> <span class="badge bg-light text-dark border text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $bill['payment_method'] ?? 'credit')) ?></span> &bull;
                    <strong>Paid:</strong> ₹<?= number_format((float)($bill['paid_amount'] ?? 0), 2) ?><br>
                    <strong>Notes:</strong> <?= htmlspecialchars($bill['notes'] ?: 'None') ?>
                </div>
                <?php if (!empty($billAttachments)): ?>
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        <?php foreach ($billAttachments as $index => $attachmentPath): ?>
                            <a href="<?= htmlspecialchars(\App\Services\CloudinaryService::url($attachmentPath)) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                <i class="bi bi-paperclip me-1"></i> Attachment <?= $index + 1 ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-success">
                <span class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.04em;">Financial Summary</span>
                <div class="d-flex justify-content-between small text-secondary mb-1">
                    <span>Subtotal (Base Value):</span>
                    <strong class="text-dark">₹<?= number_format($bill['total_amount'], 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between small text-secondary mb-1">
                    <span>Taxes (GST):</span>
                    <strong class="text-dark">₹<?= number_format($bill['tax_amount'], 2) ?></strong>
                </div>
                <hr class="my-1">
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="fw-bold text-dark small text-uppercase">Total Price:</span>
                    <h4 class="fw-bold mb-0" style="color: #16a34a;">₹<?= number_format($bill['net_amount'], 2) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Tables -->
    <?php if (($bill['source_module'] ?? 'purchase_billing') !== 'product_adding'): ?>
        <?php
        $typeLabels = [
            'rolls' => 'Fabric',
            'garment_other' => 'Other Garments Supply',
            'finished_goods' => 'Finished Goods',
        ];
        ?>
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-table text-primary fs-6"></i>
                    <h6 class="mb-0 fw-bold text-dark">Purchase Billing Items (<?= count($items) ?> Items)</h6>
                </div>
                <?php if (\App\Core\Auth::can('purchase_billing', 'export')): ?>
                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/download_items?id=' . $bill['id']) ?>" class="btn btn-outline-success btn-sm py-1 px-2 d-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Download Items (CSV)
                </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered align-middle mb-0 small">
                    <thead class="table-light text-uppercase text-secondary" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                        <tr>
                            <th class="text-center" style="width: 42px;">#</th>
                            <th>Product Type</th>
                            <th>Name</th>
                            <th>HSN</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price (₹)</th>
                            <th class="text-center">Tax Mode</th>
                            <th class="text-center">GST</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end table-success text-success fw-bold">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="10" class="text-center py-4 text-muted">No items recorded on this bill.</td></tr>
                        <?php else: ?>
                            <?php $idx = 1; foreach ($items as $item): ?>
                                <?php
                                $sType = (string)($item['section_type'] ?? 'rolls');
                                $gstType = (string)($item['gst_type'] ?? 'Exempted');
                                $gstRate = (float)($item['gst_rate'] ?? 0);
                                $taxVal = (float)($item['tax_amount'] ?? 0);
                                $totalVal = (float)($item['total_price'] ?? 0);
                                ?>
                                <tr>
                                    <td class="text-center text-muted fw-semibold"><?= $idx++ ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($typeLabels[$sType] ?? $sType) ?></span></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($item['item_name'] ?? '-') ?></td>
                                    <td class="font-monospace"><?= htmlspecialchars($item['hsn_code'] ?: '-') ?></td>
                                    <td class="text-end"><?= number_format((float)($item['qty'] ?? 0), 2) ?></td>
                                    <td class="text-end">₹<?= number_format((float)($item['unit_price'] ?? 0), 2) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($item['tax_mode'] ?? 'Exclude GST') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($gstType) ?></span>
                                        <?php if ($gstType !== 'Exempted' && $gstRate > 0): ?>
                                            <small class="text-muted d-block"><?= number_format($gstRate, 2) ?>%</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">₹<?= number_format($taxVal, 2) ?></td>
                                    <td class="text-end table-success fw-bold text-success">₹<?= number_format($totalVal, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
    <!-- Items Tables by Section (Product Adding) -->
    <?php
    $rolls = array_filter($items, fn($i) => ($i['section_type'] ?? '') === 'rolls');
    $other = array_filter($items, fn($i) => ($i['section_type'] ?? '') === 'garment_other');
    $finished = array_filter($items, fn($i) => ($i['section_type'] ?? '') === 'finished_goods');
    ?>

    <!-- 1. ROLLS TABLE (EXACT COLUMNS AS IMAGE 1) -->
    <?php if (!empty($rolls)): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-layers text-primary fs-5"></i>
                    <h6 class="mb-0 fw-bold text-dark">Fabric Rolls Inventory & Details Table (<?= count($rolls) ?> Rolls)</h6>
                </div>
                <?php if (\App\Core\Auth::can('purchase_billing', 'export')): ?>
                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/download_items?id=' . $bill['id']) ?>" class="btn btn-outline-success btn-sm py-1 px-3 d-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Items (CSV)
                </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0 text-center small">
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
                            <th>TAX</th>
                            <th class="table-success text-success fw-bold">TOTAL VALUE (TAX+TOTAL)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 1; foreach ($rolls as $r): ?>
                            <?php
                            $gstRate = (float)($r['gst_rate'] ?? 0);
                            $taxVal = (float)($r['tax_amount'] ?? 0);
                            $baseVal = (float)($r['total_price'] ?? 0);
                            $totalVal = $baseVal + $taxVal;
                            $weightKg = (float)($r['net_weight'] ?? 0);
                            $qtyRq = (int)($r['rq_qty'] ?: ($r['qty'] ?: 1));
                            $unitPrice = (float)($r['unit_price'] ?? 0);
                            $gstType = $r['gst_type'] ?? ($gstRate == 0 ? 'Exempted' : 'GST');
                            ?>
                            <tr>
                                <td><?= $idx++ ?></td>
                                <td>
                                    <?php if (!empty($r['photo_path'])): ?>
                                        <a href="<?= htmlspecialchars(\App\Services\CloudinaryService::url($r['photo_path'])) ?>" target="_blank" title="View Full Image">
                                            <img src="<?= htmlspecialchars(\App\Services\CloudinaryService::url($r['photo_path'])) ?>" class="rounded border shadow-sm" style="width: 38px; height: 38px; object-fit: cover;" alt="Roll Image">
                                        </a>
                                    <?php else: ?>
                                        <i class="bi bi-camera text-muted fs-6" title="No photo"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold font-monospace text-dark"><?= htmlspecialchars($r['item_code']) ?></td>
                                <td class="font-monospace"><?= htmlspecialchars($r['hsn_code'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($r['color'] ?: ($r['specification'] ?: '-')) ?></td>
                                <td><?= htmlspecialchars($r['pattern'] ?: '-') ?></td>
                                <td class="fw-semibold text-primary"><?= number_format($weightKg, 2) ?> kg</td>
                                <td>₹<?= number_format($unitPrice, 2) ?></td>
                                <td class="fw-semibold"><?= $qtyRq ?></td>
                                <td class="fw-semibold">₹<?= number_format($baseVal, 2) ?></td>
                                <td>
                                    <?php if ($gstType === 'Both (CGST and SGST)' && $taxVal > 0): ?>
                                        <div class="fw-bold">₹<?= number_format($taxVal, 2) ?></div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem; white-space: nowrap;">
                                            CGST: ₹<?= number_format($taxVal / 2, 2) ?> + SGST: ₹<?= number_format($taxVal / 2, 2) ?>
                                        </small>
                                    <?php else: ?>
                                        <div class="fw-bold">₹<?= number_format($taxVal, 2) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="table-success fw-bold text-success">₹<?= number_format($totalVal, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. OTHER GARMENT SUPPLIES (EXACT COLUMNS AS IMAGE 2) -->
    <?php if (!empty($other)): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam text-success fs-5"></i>
                    <h6 class="mb-0 fw-bold text-dark">Other Garment Supplies Details Table (<?= count($other) ?> Items)</h6>
                </div>
                <?php if (\App\Core\Auth::can('purchase_billing', 'export')): ?>
                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/download_items?id=' . $bill['id']) ?>" class="btn btn-outline-success btn-sm py-1 px-3 d-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Items (CSV)
                </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0 text-center small">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>ITEM NAME / DESCRIPTION</th>
                            <th>HSN</th>
                            <th>QUANTITY</th>
                            <th>UNIT</th>
                            <th>UNIT PRICE</th>
                            <th>BASE VALUE</th>
                            <th>TAX</th>
                            <th class="table-success text-success fw-bold">TOTAL VALUE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 1; foreach ($other as $o): ?>
                            <?php
                            $gstRate = (float)($o['gst_rate'] ?? 0);
                            $taxVal = (float)($o['tax_amount'] ?? 0);
                            $totalVal = (float)($o['total_price'] ?? 0) + $taxVal;
                            $gstType = $o['gst_type'] ?? ($gstRate == 0 && $taxVal <= 0 ? 'Exempted' : 'GST');
                            ?>
                            <tr>
                                <td><?= $idx++ ?></td>
                                <td class="fw-semibold text-dark text-start"><?= htmlspecialchars($o['item_name']) ?></td>
                                <td class="font-monospace"><?= htmlspecialchars($o['hsn_code'] ?: '-') ?></td>
                                <td class="fw-semibold"><?= number_format($o['qty'], 2) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($o['unit']) ?></span></td>
                                <td>₹<?= number_format($o['unit_price'], 2) ?></td>
                                <td>₹<?= number_format($o['total_price'], 2) ?></td>
                                <td>
                                    <?php if ($gstType === 'Both (CGST and SGST)' && $taxVal > 0): ?>
                                        <div class="fw-bold">₹<?= number_format($taxVal, 2) ?></div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem; white-space: nowrap;">
                                            CGST: ₹<?= number_format($taxVal / 2, 2) ?> + SGST: ₹<?= number_format($taxVal / 2, 2) ?>
                                        </small>
                                    <?php else: ?>
                                        <div class="fw-bold">₹<?= number_format($taxVal, 2) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="table-success fw-bold text-success">₹<?= number_format($totalVal, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. FINISHED GOODS (EXACT COLUMNS AS IMAGE 3) -->
    <?php if (!empty($finished)): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-tag text-purple fs-5" style="color:#8b5cf6;"></i>
                    <h6 class="mb-0 fw-bold text-dark">Finished Goods Inventory & Details Table (<?= count($finished) ?> Styles)</h6>
                </div>
                <?php if (\App\Core\Auth::can('purchase_billing', 'export')): ?>
                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/download_items?id=' . $bill['id']) ?>" class="btn btn-outline-success btn-sm py-1 px-3 d-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Items (CSV)
                </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0 text-center small">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>PRODUCT CODE</th>
                            <th>PRODUCT / HIERARCHY</th>
                            <th>COLOR</th>
                            <th>SIZE</th>
                            <th>WEIGHT</th>
                            <th>HSN</th>
                            <th>QTY</th>
                            <th>SELLING PRICE (₹)</th>
                            <th>MRP (₹)</th>
                            <th>TAX</th>
                            <th class="table-success text-success fw-bold">TOTAL VALUE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 1; foreach ($finished as $f): ?>
                            <?php
                            $gstRate = (float)($f['gst_rate'] ?? 0);
                            $taxVal = (float)($f['tax_amount'] ?? 0);
                            $totalVal = (float)($f['total_price'] ?? 0) + $taxVal;
                            $gstType = $f['gst_type'] ?? ($gstRate == 0 && $taxVal <= 0 ? 'Exempted' : 'GST');
                            $productCode = trim((string)($f['variant_product_code'] ?? ($f['item_code'] ?? '')));
                            $hierarchyPath = trim((string)($f['catalog_hierarchy_path'] ?? ($f['category_name'] ?? '')));
                            $styleCode = trim((string)($f['catalog_style_code'] ?? ''));
                            $variantAttrs = !empty($f['variant_attributes']) ? json_decode((string)$f['variant_attributes'], true) : [];
                            $fgColor = trim((string)($f['color'] ?? ($variantAttrs['color'] ?? '')));
                            $fgSize = trim((string)($f['variant_size'] ?? ($f['specification'] ?? '')));
                            $fgWeight = (float)($f['net_weight'] ?? ($f['variant_weight'] ?? 0));
                            $fgSelling = (float)($f['selling_price'] ?? ($f['variant_selling_price'] ?? ($f['unit_price'] ?? 0)));
                            $fgMrp = (float)($f['mrp'] ?? ($f['variant_mrp'] ?? 0));
                            ?>
                            <tr>
                                <td><?= $idx++ ?></td>
                                <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($productCode !== '' ? $productCode : '-') ?></td>
                                <td class="text-start">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($f['item_name']) ?></div>
                                    <?php if ($hierarchyPath !== '' || $styleCode !== ''): ?>
                                        <small class="text-muted d-block">
                                            <?= htmlspecialchars($hierarchyPath) ?><?= ($hierarchyPath !== '' && $styleCode !== '') ? ' • ' : '' ?><?= $styleCode !== '' ? htmlspecialchars($styleCode) : '' ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($fgColor !== '' ? $fgColor : '-') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($fgSize !== '' ? $fgSize : '-') ?></span></td>
                                <td><?= $fgWeight > 0 ? number_format($fgWeight, 2) . ' kg' : '-' ?></td>
                                <td class="font-monospace"><?= htmlspecialchars($f['hsn_code'] ?: '-') ?></td>
                                <td class="fw-bold"><?= (int)$f['qty'] ?></td>
                                <td class="fw-semibold">₹<?= number_format($fgSelling, 2) ?></td>
                                <td><?= $fgMrp > 0 ? '₹' . number_format($fgMrp, 2) : '-' ?></td>
                                <td>
                                    <?php if ($gstType === 'Both (CGST and SGST)' && $taxVal > 0): ?>
                                        <div class="fw-bold">₹<?= number_format($taxVal, 2) ?></div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem; white-space: nowrap;">
                                            CGST: ₹<?= number_format($taxVal / 2, 2) ?> + SGST: ₹<?= number_format($taxVal / 2, 2) ?>
                                        </small>
                                    <?php else: ?>
                                        <div class="fw-bold">₹<?= number_format($taxVal, 2) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="table-success fw-bold text-success">₹<?= number_format($totalVal, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
    </div><!-- End #paneInteractive -->

    <!-- ============================================== -->
    <!-- PANE 2: OFFICIAL TAX INVOICE PRINT FORMAT      -->
    <!-- ============================================== -->
    <div class="tab-pane fade" id="paneInvoicePrint" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white invoice-print-container" id="printableInvoiceCard">
            <!-- Printable Document Body -->
            <div class="invoice-box border p-4 rounded-3 bg-white text-dark">
                <!-- Header: Concern Info & Invoice Identity -->
                <div class="row pb-3 mb-3 border-bottom align-items-center">
                    <div class="col-8">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <?php if (!empty($bill['concern_logo'])): ?>
                                <img src="<?= htmlspecialchars(\App\Services\CloudinaryService::url($bill['concern_logo'])) ?>" alt="<?= htmlspecialchars($bill['concern_name'] ?: 'Logo') ?>" style="max-height: 44px; max-width: 130px; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-building fs-3 text-primary"></i>
                            <?php endif; ?>
                            <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($bill['concern_name'] ?: 'GRM Inventory') ?></h3>
                        </div>
                        <div class="text-muted small">
                            <strong>Concern Unit:</strong> <?= htmlspecialchars($bill['concern_code'] ?: 'GRM') ?>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="text-muted small text-uppercase fw-bold">Purchase Bill No.</div>
                        <h4 class="fw-bold font-monospace text-primary mb-1"><?= htmlspecialchars($bill['bill_no']) ?></h4>
                        <div class="small text-muted"><strong>Bill Date:</strong> <?= date('d M Y', strtotime($bill['bill_date'])) ?></div>
                        <div class="small text-muted"><strong>Supplier Inv #:</strong> <?= htmlspecialchars($bill['supplier_invoice_no'] ?: 'N/A') ?></div>
                    </div>
                </div>

                <!-- Parties: Supplier vs Consignee -->
                <div class="row g-3 pb-3 mb-3 border-bottom small">
                    <div class="col-6 border-end">
                        <div class="fw-bold text-uppercase text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Supplier / Vendor (Billed By)</div>
                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($bill['supplier_name']) ?></div>
                        <?php if (!empty($bill['supplier_company'])): ?>
                            <div class="text-secondary fw-semibold"><?= htmlspecialchars($bill['supplier_company']) ?></div>
                        <?php endif; ?>
                        <div class="text-muted mt-1">
                            Phone: <?= htmlspecialchars($bill['supplier_phone'] ?: '-') ?><br>
                            Location: <?= htmlspecialchars($bill['supplier_district'] ?: '-') ?>, <?= htmlspecialchars($bill['supplier_state'] ?: 'Tamil Nadu') ?><br>
                            <span class="font-monospace fw-semibold text-dark">GSTIN: <?= htmlspecialchars($bill['supplier_gstin'] ?: 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="col-6 ps-3">
                        <div class="fw-bold text-uppercase text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Billed & Delivered To (Consignee)</div>
                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($bill['concern_name'] ?: 'GRM Inventory') ?></div>
                        <div class="text-muted mt-1">
                            <strong>Payment Status:</strong> <span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars($bill['payment_status']) ?></span> &bull; 
                            <strong>Method:</strong> <span class="text-capitalize fw-semibold text-dark"><?= htmlspecialchars(str_replace('_', ' ', $bill['payment_method'] ?? 'credit')) ?></span><br>
                            <strong>Paid Amount:</strong> ₹<?= number_format((float)($bill['paid_amount'] ?? 0), 2) ?><br>
                            <strong>Inward Status:</strong> <span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars($bill['inward_status']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Consolidated Items Table -->
                <div class="table-responsive mb-3 print-table-container">
                    <table class="table table-bordered table-sm align-middle small mb-0 print-items-table">
                        <thead class="table-light">
                            <tr class="fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                                <th rowspan="2" class="text-center" style="width: 3%;">#</th>
                                <th rowspan="2" class="text-start" style="width: 10%;">Item / Ref</th>
                                <th rowspan="2" class="text-start" style="width: 21%;">Description & Specification</th>
                                <th rowspan="2" class="text-center" style="width: 7%;">HSN</th>
                                <th rowspan="2" class="text-center" style="width: 9%;">Qty / Weight</th>
                                <th rowspan="2" class="text-end" style="width: 12%;">Rate / Taxable</th>
                                <th colspan="2" class="text-center" style="width: 24%;">GST</th>
                                <th rowspan="2" class="text-end" style="width: 14%;">Total (₹)</th>
                            </tr>
                            <tr class="fw-bold text-uppercase">
                                <th class="text-center">CGST</th>
                                <th class="text-center">SGST</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $printIdx = 1;
                            $printBaseTotal = 0.0;
                            $printTaxTotal = 0.0;
                            $printNetTotal = 0.0;
                            $printQtyTotal = 0.0;
                            $printWtTotal = 0.0;
                            $printCgstTotal = 0.0;
                            $printSgstTotal = 0.0;
                            $printIgstTotal = 0.0;

                            foreach ($items as $it):
                                $secType = $it['section_type'] ?? '';
                                $secName = match ($secType) {
                                    'rolls' => 'Fabric Roll',
                                    'garment_other' => 'Other Supply',
                                    'finished_goods' => 'Finished Good',
                                    default => 'General'
                                };
                                $qty = (float)($it['qty'] ?? 1);
                                $wt = (float)($it['net_weight'] ?? 0);
                                $price = (float)($it['unit_price'] ?? 0);
                                $base = (float)($it['total_price'] ?? 0);
                                $tax = (float)($it['tax_amount'] ?? 0);
                                $lineTotal = $base + $tax;
                                $gstRate = (float)($it['gst_rate'] ?? 0);
                                $gstType = trim((string)($it['gst_type'] ?? ''));
                                if ($gstType === '') {
                                    $gstType = ($gstRate == 0 && $tax <= 0) ? 'Exempted' : 'GST';
                                }
                                $cgstRate = 0.0;
                                $sgstRate = 0.0;
                                $igstRate = 0.0;
                                $cgstTax = 0.0;
                                $sgstTax = 0.0;
                                $igstTax = 0.0;
                                if ($gstType === 'Both (CGST and SGST)') {
                                    $cgstRate = round($gstRate / 2, 2);
                                    $sgstRate = $gstRate - $cgstRate;
                                    $cgstTax = round($tax / 2, 2);
                                    $sgstTax = $tax - $cgstTax;
                                } elseif ($gstType === 'CGST') {
                                    $cgstRate = $gstRate;
                                    $cgstTax = $tax;
                                } elseif ($gstType === 'SGST') {
                                    $sgstRate = $gstRate;
                                    $sgstTax = $tax;
                                } elseif ($gstType !== 'Exempted') {
                                    $igstRate = $gstRate;
                                    $igstTax = $tax;
                                }

                                $printBaseTotal += $base;
                                $printTaxTotal += $tax;
                                $printNetTotal += $lineTotal;
                                $printQtyTotal += $qty;
                                $printWtTotal += $wt;
                                $printCgstTotal += $cgstTax;
                                $printSgstTotal += $sgstTax;
                                $printIgstTotal += $igstTax;

                                // Item Code display
                                $rawCode = trim($it['item_code'] ?? '');
                                if ($secType === 'rolls') {
                                    $dispCode = (!empty($rawCode) && !preg_match('/^roll/i', $rawCode)) ? ('Roll #' . $rawCode) : ($rawCode ?: 'Roll #' . $printIdx);
                                } else {
                                    $dispCode = $rawCode ?: '-';
                                }

                                // Title formatting: avoid repetitive "1" or numeric title when pattern exists
                                $rawName = trim($it['item_name'] ?? '');
                                $isGenericRoll = ($secType === 'rolls') && ($rawName === $rawCode || is_numeric($rawName) || preg_match('/^roll\s*#?\d+$/i', $rawName));
                                if ($isGenericRoll && !empty($it['pattern'])) {
                                    $displayTitle = 'Fabric &bull; ' . htmlspecialchars(ucwords($it['pattern']));
                                } elseif (!empty($rawName)) {
                                    $displayTitle = htmlspecialchars($rawName);
                                } elseif (!empty($it['pattern'])) {
                                    $displayTitle = htmlspecialchars(ucwords($it['pattern']));
                                } else {
                                    $displayTitle = $secName;
                                }

                                // Specification details deduplication
                                $specs = [];
                                if (!empty($it['pattern']) && stripos($displayTitle, $it['pattern']) === false) {
                                    $specs[] = 'Pattern: ' . htmlspecialchars($it['pattern']);
                                }
                                if (!empty($it['color'])) {
                                    $specs[] = 'Color: ' . htmlspecialchars(ucwords($it['color']));
                                }
                                if (!empty($it['specification'])) {
                                    $cleanSpec = trim($it['specification']);
                                    $colorVal  = trim($it['color'] ?? '');
                                    $patVal    = trim($it['pattern'] ?? '');
                                    if (strcasecmp($cleanSpec, $colorVal) !== 0 && strcasecmp($cleanSpec, $patVal) !== 0 && strcasecmp($cleanSpec, $rawName) !== 0) {
                                        $specs[] = htmlspecialchars($cleanSpec);
                                    }
                                }

                                // Qty unit clean display
                                $unitName = trim($it['unit'] ?? '');
                                if ($unitName === 'RQ' || empty($unitName)) {
                                    $unitName = ($secType === 'rolls') ? 'Rolls' : 'Pcs';
                                }
                            ?>
                                <tr>
                                    <td class="text-center fw-semibold text-muted"><?= $printIdx++ ?></td>
                                    <td>
                                        <div class="fw-bold font-monospace text-dark nowrap-num"><?= htmlspecialchars($dispCode) ?></div>
                                        <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.65rem; font-weight: normal;"><?= $secName ?></span>
                                    </td>
                                    <td class="text-start">
                                        <div class="fw-bold text-dark"><?= $displayTitle ?></div>
                                        <?php if (!empty($specs)): ?>
                                            <div class="text-muted small mt-1" style="font-size: 0.72rem; line-height: 1.25;"><?= implode(' &bull; ', $specs) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center font-monospace small nowrap-num"><?= htmlspecialchars($it['hsn_code'] ?: '-') ?></td>
                                    <td class="text-center">
                                        <div class="fw-bold text-dark nowrap-num"><?= (float)$qty ?> <span class="fw-normal text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($unitName) ?></span></div>
                                        <?php if ($wt > 0): ?>
                                            <div class="text-secondary small mt-0 nowrap-num" style="font-size: 0.70rem;"><?= number_format($wt, 2) ?> kg</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-semibold text-dark nowrap-num">₹<?= number_format($price, 2) ?></div>
                                        <?php if (($it['tax_mode'] ?? '') === 'Include GST'): ?>
                                            <div class="text-muted small nowrap-num" style="font-size: 0.68rem;">Taxable: ₹<?= number_format($base, 2) ?></div>
                                            <div class="text-muted" style="font-size: 0.62rem;">(Incl. GST)</div>
                                        <?php else: ?>
                                            <div class="text-muted small nowrap-num" style="font-size: 0.68rem;">Base: ₹<?= number_format($base, 2) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($igstRate > 0 || $igstTax > 0): ?>
                                        <td colspan="2" class="text-center tax-component">
                                            <div class="tax-component-rate"><?= $igstRate > 0 ? 'IGST ' . number_format($igstRate, 2) . '%' : '' ?></div>
                                            <div class="tax-component-amount"><?= $igstTax > 0 ? '₹' . number_format($igstTax, 2) : '-' ?></div>
                                        </td>
                                    <?php else: ?>
                                        <td class="text-center tax-component">
                                            <?php if ($cgstRate > 0 || $cgstTax > 0): ?>
                                                <?php if ($cgstRate > 0): ?><div class="tax-component-rate"><?= number_format($cgstRate, 2) ?>%</div><?php endif; ?>
                                                <div class="tax-component-amount"><?= $cgstTax > 0 ? '₹' . number_format($cgstTax, 2) : '₹0.00' ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center tax-component">
                                            <?php if ($sgstRate > 0 || $sgstTax > 0): ?>
                                                <?php if ($sgstRate > 0): ?><div class="tax-component-rate"><?= number_format($sgstRate, 2) ?>%</div><?php endif; ?>
                                                <div class="tax-component-amount"><?= $sgstTax > 0 ? '₹' . number_format($sgstTax, 2) : '₹0.00' ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-end fw-bold text-dark nowrap-num">₹<?= number_format($lineTotal, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end text-uppercase" style="font-size: 0.75rem;">Totals:</td>
                                <td class="text-center nowrap-num">
                                    <div><?= number_format($printQtyTotal, 0) ?> Qty</div>
                                    <?php if ($printWtTotal > 0): ?>
                                        <div class="text-muted fw-normal" style="font-size: 0.70rem;"><?= number_format($printWtTotal, 2) ?> kg</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end nowrap-num">
                                    <div>₹<?= number_format($printBaseTotal, 2) ?></div>
                                    <div class="text-muted fw-normal" style="font-size: 0.65rem;">(Taxable Base)</div>
                                </td>
                                <td class="text-center nowrap-num">
                                    <div>₹<?= number_format($printCgstTotal, 2) ?></div>
                                </td>
                                <td class="text-center nowrap-num">
                                    <div>₹<?= number_format($printSgstTotal, 2) ?></div>
                                </td>
                                <td class="text-end text-success fs-6 nowrap-num">₹<?= number_format($printNetTotal, 2) ?></td>
                            </tr>
                            <?php if ($printIgstTotal > 0): ?>
                                <tr>
                                    <td colspan="6" class="text-end text-uppercase" style="font-size: 0.72rem;">IGST Total:</td>
                                    <td colspan="2" class="text-center nowrap-num">₹<?= number_format($printIgstTotal, 2) ?></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>

                <!-- Financial Calculation & Remarks -->
                <div class="row pt-2 align-items-start">
                    <div class="col-7">
                        <div class="border rounded p-2 bg-light small mb-2">
                            <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.05em;">Amount Chargeable (in words)</div>
                            <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= amountToWordsINR($bill['net_amount']) ?></div>
                        </div>

                        <?php if (!empty($bill['notes'])): ?>
                            <div class="border rounded p-2 bg-white small mb-2">
                                <strong class="text-secondary" style="font-size: 0.70rem; text-transform: uppercase;">Consignment / Inward Notes:</strong>
                                <div class="text-dark mt-1" style="font-size: 0.80rem;"><?= htmlspecialchars($bill['notes']) ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-3 text-muted small mt-2" style="font-size: 0.75rem;">
                            <div><strong>Total Line Items:</strong> <?= count($items) ?></div>
                            <div>&bull;</div>
                            <div><strong>Total Qty:</strong> <?= number_format($printQtyTotal, 2) ?></div>
                            <?php if ($printWtTotal > 0): ?>
                                <div>&bull;</div>
                                <div><strong>Total Net Wt:</strong> <?= number_format($printWtTotal, 2) ?> kg</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-5">
                        <div class="border rounded p-3 bg-light small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Taxable Base Amount:</span>
                                <span class="fw-semibold text-dark">₹<?= number_format($bill['total_amount'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Total Tax Amount (GST):</span>
                                <span class="fw-semibold text-dark">₹<?= number_format($bill['tax_amount'], 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 align-items-center">
                                <div>
                                    <div class="fw-bold fs-6 text-dark">Invoice Grand Total:</div>
                                    <div class="text-muted" style="font-size: 0.68rem;">(Inclusive of all taxes)</div>
                                </div>
                                <div class="fw-bold fs-5 text-success">₹<?= number_format($bill['net_amount'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signature Block -->
                <div class="row text-center pt-4 mt-3 border-top small text-muted signature-block">
                    <div class="col-4">
                        <div class="pt-4 border-top border-dark mx-3 fw-semibold text-dark">Store In-Charge / Receiver</div>
                    </div>
                    <div class="col-4">
                        <div class="pt-4 border-top border-dark mx-3 fw-semibold text-dark">Quality / QC Inspector</div>
                    </div>
                    <div class="col-4">
                        <div class="pt-4 border-top border-dark mx-3 fw-semibold text-dark">Authorized Signatory</div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- End #paneInvoicePrint -->
    </div><!-- End #viewModeTabContent -->
</div>

<!-- Print Styles & Scripts -->
<style>
/* Screen & Common Styles for Invoice Preview */
.print-items-table {
    border-collapse: collapse !important;
    table-layout: fixed;
    width: 100%;
}
.print-items-table thead th {
    white-space: normal !important;
    padding: 6px 5px !important;
    font-size: 0.72rem !important;
    letter-spacing: 0.02em;
    line-height: 1.15;
}
.print-items-table td {
    padding: 6px 5px !important;
    vertical-align: middle !important;
}
.tax-component {
    font-size: 0.72rem;
    line-height: 1.2;
}
.tax-component-rate {
    color: #64748b;
    white-space: nowrap;
}
.tax-component-amount {
    font-weight: 600;
    white-space: nowrap;
}
.nowrap-num {
    white-space: nowrap !important;
    word-break: keep-all !important;
}

@media print {
    /* Hide layout navigation, sidebars and headers */
    #sidebar,
    .top-navbar,
    .d-print-none,
    nav,
    header,
    .btn,
    .nav-pills,
    .alert {
        display: none !important;
    }

    #main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 9pt !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .container-fluid {
        padding: 0 !important;
        max-width: 100% !important;
    }

    /* Force invoice print card to be visible even if other tab was active */
    #paneInteractive {
        display: none !important;
    }

    #paneInvoicePrint {
        display: block !important;
        opacity: 1 !important;
    }

    .invoice-print-container {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .invoice-box {
        border: 1px solid #1e293b !important;
        border-radius: 4px !important;
        padding: 12px 14px !important;
        margin: 0 !important;
        background: #ffffff !important;
        page-break-inside: auto;
    }

    .table-responsive {
        overflow: visible !important;
        display: block !important;
        width: 100% !important;
    }

    .print-items-table {
        width: 100% !important;
        table-layout: fixed !important;
        margin-bottom: 8px !important;
    }

    .print-items-table th,
    .print-items-table td {
        border: 1px solid #475569 !important;
        color: #000000 !important;
        padding: 3px 4px !important;
        font-size: 7.5pt !important;
        vertical-align: middle !important;
        word-wrap: break-word !important;
        overflow-wrap: anywhere !important;
    }

    .print-items-table thead th {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 700 !important;
        white-space: normal !important;
        line-height: 1.15 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .print-items-table thead {
        display: table-header-group;
    }

    .print-items-table tbody tr,
    .print-items-table tfoot tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .tax-component {
        font-size: 7pt !important;
    }

    .tax-component-rate {
        white-space: nowrap !important;
    }

    .tax-component-amount {
        white-space: nowrap !important;
    }

    .print-items-table tfoot td {
        background-color: #f8fafc !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .nowrap-num {
        white-space: nowrap !important;
        word-break: keep-all !important;
    }

    .print-invoice-badge {
        display: inline-block !important;
        background-color: #0f172a !important;
        color: #ffffff !important;
        padding: 3px 6px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .signature-block {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        margin-top: 20px !important;
        padding-top: 15px !important;
    }

    @page {
        size: A4 portrait;
        margin: 8mm 10mm;
    }
}
</style>

<script>
function triggerInvoicePrint() {
    const printTab = document.getElementById('tabInvoicePrintBtn');
    if (printTab) {
        printTab.click();
    }
    setTimeout(() => {
        window.print();
    }, 200);
}
</script>
