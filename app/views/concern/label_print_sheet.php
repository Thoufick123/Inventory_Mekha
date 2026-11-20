<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Print Labels - GRM Inventory') ?></title>
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap-icons.min.css') ?>">
    <style>
        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 1.5rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
        }
        .print-toolbar {
            background: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .label-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            max-width: 980px;
            margin: 0 auto;
        }
        .label-grid.thermal-mode {
            grid-template-columns: 1fr;
            max-width: 420px;
            gap: 16px;
        }
        .label-sticker {
            background: #ffffff;
            border: 1.5px solid #0f172a;
            border-radius: 10px;
            padding: 8px 12px;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
            break-inside: avoid;
            box-sizing: border-box;
            min-height: 140px;
            position: relative;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .label-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            padding-bottom: 4px;
        }
        .concern-check-icon {
            background-color: #0d6efd;
            color: #ffffff;
            width: 17px;
            height: 17px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            margin-right: 6px;
            flex-shrink: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .concern-name-text {
            font-weight: 800;
            font-size: 0.82rem;
            color: #0f172a;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 170px;
        }
        .ref-pill-badge {
            background-color: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
            font-family: monospace;
            font-size: 0.70rem;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 12px;
            white-space: nowrap;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .size-pill-badge {
            background-color: #fbbf24;
            color: #0f172a;
            font-family: monospace;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 8px;
            white-space: nowrap;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .label-divider {
            border-top: 1px dashed #cbd5e1;
            margin: 4px 0 6px 0;
        }
        .label-body-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }
        .label-qr-wrapper {
            width: 76px;
            height: 76px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
        }
        .label-qr-box {
            width: 76px;
            height: 76px;
        }
        .label-details-stack {
            flex: 1;
            overflow: hidden;
            line-height: 1.35;
            font-size: 0.76rem;
        }
        .label-item-title {
            font-size: 0.72rem;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.25;
            letter-spacing: 0.02em;
        }
        .label-detail-row {
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.75rem;
        }
        .label-detail-row .detail-label {
            color: #64748b;
            font-weight: 500;
            margin-right: 3px;
        }
        .label-detail-row .detail-val {
            color: #0f172a;
            font-weight: 600;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .print-toolbar {
                display: none !important;
            }
            .label-grid {
                max-width: 100% !important;
                gap: 8px !important;
            }
            .label-sticker {
                border: 1.2px solid #000000 !important;
                box-shadow: none !important;
                break-inside: avoid;
            }
            .concern-check-icon {
                background-color: #000000 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .ref-pill-badge {
                border: 1px solid #000000 !important;
                color: #000000 !important;
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

<?php
$concern = \App\Core\Auth::concern();
$concernName = $labelData['concern_name'] ?? ($concern['name'] ?? 'GRM Inventory');
$refNo       = $labelData['ref_no'] ?? ($labelData['cost_or_ref'] ?? 'REF-001');
$headerTitle = $labelData['header_title'] ?? ($labelData['item_title'] ?? 'Garment Item');
$label1Name  = $labelData['label_1_name'] ?? 'Item';
$label1Val   = $labelData['label_1_val'] ?? ($labelData['item_title'] ?? '-');
$label2Name  = $labelData['label_2_name'] ?? 'Color';
$label2Val   = $labelData['label_2_val'] ?? ($labelData['color_or_spec'] ?? 'STANDARD');
$designVal   = $labelData['design'] ?? ($labelData['label_3_val'] ?? ($labelData['pattern'] ?? 'STANDARD'));
$variantAttributes = $labelData['variant_attributes'] ?? [];
$variantAttributes = is_array($variantAttributes) ? $variantAttributes : [];
$priceVal    = (float)($labelData['price'] ?? ($labelData['price_mrp'] ?? 0));
$tagCode     = $labelData['tag_code'] ?? ($labelData['item_code'] ?? 'SKU');
$qrText      = $qrCodeText ?? ($labelData['qr_payload'] ?? $tagCode);
$badge       = $labelData['section_badge'] ?? 'STANDARD';
$viewOnly    = !empty($viewOnly);
$isProduction = ($labelType === 'production') 
    || (($labelData['section_title'] ?? '') === 'PRODUCTION FINISHED GARMENT')
    || (($labelData['stock_tab'] ?? '') === 'production');

// Precalculate variant summary for batch mode
$variantSummary = [];
if (!empty($multiLabels)) {
    foreach ($multiLabels as $_lbl) {
        $sz = $_lbl['variant_size'] ?? '—';
        if (!isset($variantSummary[$sz])) {
            $variantSummary[$sz] = ['sku' => $_lbl['sku_code'] ?? '', 'count' => 0];
        }
        $variantSummary[$sz]['count']++;
    }
}
?>

<div class="print-toolbar">
    <!-- Top Row: Title + Action buttons in one clean, un-wrapped row -->
    <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2 text-dark">
            <i class="bi <?= $viewOnly ? 'bi-eye' : 'bi-printer' ?> text-primary"></i>
            <span><?= $viewOnly ? 'Finished Goods Label' : 'Print Sheet Preview' ?></span>
            <?php if (!$viewOnly): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary fs-6">Job #<?= (int)($jobId ?? 1) ?></span>
            <?php endif; ?>
        </h5>
        <div class="d-flex align-items-center gap-2 flex-wrap" style="white-space: nowrap;">
            <?php if (!$viewOnly): ?>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" id="btnLayoutA4" onclick="setLayout('a4')">
                        <i class="bi bi-grid-3x3"></i> A4 Grid (3 Col)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnLayoutThermal" onclick="setLayout('thermal')">
                        <i class="bi bi-card-text"></i> Thermal Roll (1 Col)
                    </button>
                </div>
                <button class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="window.print()">
                    <i class="bi bi-printer-fill me-1"></i> Print Labels Now
                </button>
            <?php endif; ?>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.close()">Close</button>
        </div>
    </div>

    <!-- Bottom Row: Batch details and variant pills spanning full width -->
    <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2 pt-2 border-top text-muted small">
        <div class="d-flex align-items-center gap-1 flex-wrap">
            <span class="badge bg-light text-dark border"><?= htmlspecialchars($labelData['section_title'] ?? 'PRODUCT LABEL') ?></span>
            <span class="badge bg-primary-subtle text-primary border"><?= htmlspecialchars($badge) ?></span>
            <?php if (!empty($multiLabels)): ?>
                <span>
                    <?= $viewOnly ? 'Viewing' : 'Printing' ?>
                    <strong><?= (int)$qty ?> <?= $viewOnly ? 'label' : 'labels' ?></strong>
                    <?php if (!$viewOnly || ($variantCount ?? count($multiLabels)) !== 1): ?>
                        across <strong><?= (int)($variantCount ?? count($multiLabels)) ?> variants</strong>
                    <?php endif; ?>
                    <?php if (($labelType ?? '') === 'production'): ?>
                        for Batch <strong>#<?= htmlspecialchars($batchNo ?? 'BATCH') ?></strong>
                    <?php elseif (($labelType ?? '') === 'purchase_fg'): ?>
                        for Bill <strong>#<?= htmlspecialchars($batchNo ?: ($labelData['ref_no'] ?? '')) ?></strong>
                    <?php endif; ?>
                    &bull; Concern: <strong><?= htmlspecialchars($concernName) ?></strong>
                </span>
            <?php else: ?>
                <span><?= $viewOnly ? 'Viewing' : 'Printing' ?> <strong><?= (int)$qty ?> <?= $viewOnly ? 'label' : 'copies' ?></strong> of <strong><?= htmlspecialchars(!empty($headerTitle) ? $headerTitle : $label1Val) ?></strong> (<?= htmlspecialchars($tagCode) ?>) &bull; Concern: <strong><?= htmlspecialchars($concernName) ?></strong></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($multiLabels) && !empty($variantSummary)): ?>
            <div class="d-flex align-items-center gap-1 flex-wrap">
                <span class="text-secondary small fw-semibold me-1">Variants:</span>
                <?php foreach ($variantSummary as $sz => $info): ?>
                    <span class="size-pill-badge" style="font-size:0.68rem;padding:2px 7px;">
                        <?= strtoupper(htmlspecialchars($sz)) ?> &times;<?= $info['count'] ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="label-grid" id="labelGrid">
    <?php if (!empty($multiLabels)): ?>
        <?php foreach ($multiLabels as $lbl): 
            $cName     = $lbl['concern_name'] ?? $concernName;
            $rNo       = $lbl['ref_no'] ?? 'REF-001';
            $hTitle    = $lbl['header_title'] ?? ($lbl['item_title'] ?? '');
            $l1Name    = $lbl['label_1_name'] ?? 'Program';
            $l1Val     = $lbl['label_1_val'] ?? '-';
            $l2Name    = $lbl['label_2_name'] ?? 'Color';
            $l2Val     = $lbl['label_2_val'] ?? 'STANDARD';
            $dVal      = $lbl['design'] ?? ($lbl['label_3_val'] ?? 'STANDARD');
            $lblVariantAttributes = $lbl['variant_attributes'] ?? [];
            $lblVariantAttributes = is_array($lblVariantAttributes) ? $lblVariantAttributes : [];
            $pVal      = (float)($lbl['price'] ?? ($lbl['price_mrp'] ?? 0));
            $tCode     = $lbl['tag_code'] ?? ($lbl['item_code'] ?? 'SKU');
            $iQr       = $lbl['qr_payload'] ?? $tCode;
            $vSize     = !empty($lbl['variant_size']) ? $lbl['variant_size'] : preg_replace('/^SIZE:\s*/i', '', $dVal);
            if (empty($vSize) || $vSize === 'STANDARD') {
                $vSize = 'Free';
            }
        ?>
            <div class="label-sticker">
                <div class="label-header-bar">
                    <div class="d-flex align-items-center">
                        <span class="concern-check-icon"><i class="bi bi-check-lg"></i></span>
                        <span class="concern-name-text"><?= htmlspecialchars($cName) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="size-pill-badge"><?= strtoupper(htmlspecialchars($vSize)) ?></span>
                        <span class="ref-pill-badge"><?= htmlspecialchars($rNo) ?></span>
                    </div>
                </div>
                <div class="label-divider"></div>
                <div class="label-body-content">
                    <div class="label-qr-wrapper">
                        <div class="label-qr-box qr-container" data-code="<?= htmlspecialchars($iQr) ?>"></div>
                    </div>
                    <div class="label-details-stack">
                        <?php if (!empty($hTitle)): ?>
                            <div class="label-item-title"><?= htmlspecialchars($hTitle) ?></div>
                        <?php endif; ?>
                        <?php if ($isProduction): ?>
                            <!-- PRODUCTION: Clean layout without Program or Color, Size instead of Design -->
                            <div class="label-detail-row">
                                <span class="detail-label">Size:</span>
                                <span class="detail-val text-uppercase fw-bold"><?= htmlspecialchars($vSize) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Price:</span>
                                <span class="detail-val fw-bold text-success">₹<?= number_format($pVal, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Code:</span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($tCode) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="label-detail-row">
                                <span class="detail-label"><?= htmlspecialchars($l1Name) ?>:</span>
                                <span class="detail-val"><?= htmlspecialchars($l1Val) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label"><?= htmlspecialchars($l2Name) ?>:</span>
                                <span class="detail-val text-uppercase"><?= htmlspecialchars($l2Val) ?></span>
                            </div>
                            <?php foreach ($lblVariantAttributes as $attributeName => $attributeValue): ?>
                                <div class="label-detail-row">
                                    <span class="detail-label"><?= htmlspecialchars((string) $attributeName) ?>:</span>
                                    <span class="detail-val text-uppercase"><?= htmlspecialchars((string) $attributeValue) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($lblVariantAttributes) || ($dVal !== '' && strtoupper($dVal) !== 'STANDARD')): ?>
                                <div class="label-detail-row">
                                    <span class="detail-label">Design:</span>
                                    <span class="detail-val text-uppercase"><?= htmlspecialchars($dVal) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="label-detail-row">
                                <span class="detail-label">Price:</span>
                                <span class="detail-val fw-bold text-success">₹<?= number_format($pVal, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Code:</span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($tCode) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?php 
        $sSize = !empty($labelData['variant_size']) ? $labelData['variant_size'] : preg_replace('/^SIZE:\s*/i', '', $designVal);
        if (empty($sSize) || $sSize === 'STANDARD') {
            $sSize = 'Free';
        }
        ?>
        <?php for ($i = 0; $i < $qty; $i++): ?>
            <div class="label-sticker">
                <!-- Top Header: Concern Name Dynamically & Reference Badge (Image 2 Reference) -->
                <div class="label-header-bar">
                    <div class="d-flex align-items-center">
                        <span class="concern-check-icon"><i class="bi bi-check-lg"></i></span>
                        <span class="concern-name-text"><?= htmlspecialchars($concernName) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <?php if ($isProduction): ?>
                            <span class="size-pill-badge"><?= strtoupper(htmlspecialchars($sSize)) ?></span>
                        <?php endif; ?>
                        <span class="ref-pill-badge"><?= htmlspecialchars($refNo) ?></span>
                    </div>
                </div>

                <!-- Dashed Separator Line -->
                <div class="label-divider"></div>

                <!-- Body: QR on Left, Details Stack on Right -->
                <div class="label-body-content">
                    <div class="label-qr-wrapper">
                        <div class="label-qr-box qr-container" data-code="<?= htmlspecialchars($qrText) ?>"></div>
                    </div>

                    <div class="label-details-stack">
                        <?php if (!empty($headerTitle)): ?>
                            <div class="label-item-title"><?= htmlspecialchars($headerTitle) ?></div>
                        <?php endif; ?>
                        <?php if ($isProduction): ?>
                            <!-- PRODUCTION: Clean layout without Program or Color, Size instead of Design -->
                            <div class="label-detail-row">
                                <span class="detail-label">Size:</span>
                                <span class="detail-val text-uppercase fw-bold"><?= htmlspecialchars($sSize) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Price:</span>
                                <span class="detail-val fw-bold text-success">₹<?= number_format($priceVal, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Code:</span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($tagCode) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="label-detail-row">
                                <span class="detail-label"><?= htmlspecialchars($label1Name) ?>:</span>
                                <span class="detail-val"><?= htmlspecialchars($label1Val) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label"><?= htmlspecialchars($label2Name) ?>:</span>
                                <span class="detail-val text-uppercase"><?= htmlspecialchars($label2Val) ?></span>
                            </div>
                            <?php foreach ($variantAttributes as $attributeName => $attributeValue): ?>
                                <div class="label-detail-row">
                                    <span class="detail-label"><?= htmlspecialchars((string) $attributeName) ?>:</span>
                                    <span class="detail-val text-uppercase"><?= htmlspecialchars((string) $attributeValue) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($variantAttributes) || ($designVal !== '' && strtoupper($designVal) !== 'STANDARD')): ?>
                                <div class="label-detail-row">
                                    <span class="detail-label">Design:</span>
                                    <span class="detail-val text-uppercase"><?= htmlspecialchars($designVal) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="label-detail-row">
                                <span class="detail-label">Price:</span>
                                <span class="detail-val fw-bold text-success">₹<?= number_format($priceVal, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Code:</span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($tagCode) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    <?php endif; ?>
</div>

<script src="<?= \App\Core\Controller::url('/assets/js/qrcode.min.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const containers = document.querySelectorAll('.qr-container');
    containers.forEach(el => {
        const code = el.getAttribute('data-code') || "<?= htmlspecialchars($qrText ?? '') ?>";
        if (code) {
            new QRCode(el, {
                text: code,
                width: 80,
                height: 80,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
            });
        }
    });
});

function setLayout(mode) {
    const grid = document.getElementById('labelGrid');
    const btnA4 = document.getElementById('btnLayoutA4');
    const btnThermal = document.getElementById('btnLayoutThermal');
    if (mode === 'thermal') {
        grid.classList.add('thermal-mode');
        btnThermal.classList.add('active');
        btnA4.classList.remove('active');
    } else {
        grid.classList.remove('thermal-mode');
        btnA4.classList.add('active');
        btnThermal.classList.remove('active');
    }
}
</script>

</body>
</html>
