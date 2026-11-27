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
        .label-sticker.pa-label-sticker {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 14px 12px;
            min-height: 158px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
        }
        .pa-label-concern-header {
            text-align: center;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #475569;
            text-transform: uppercase;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pa-label-body {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex: 1;
            padding: 2px 0 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .pa-label-qr-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-shrink: 0;
        }
        .pa-label-qr-frame {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pa-label-qr-frame .label-qr-box {
            width: 72px;
            height: 72px;
        }
        .pa-label-qr-frame .label-qr-box img,
        .pa-label-qr-frame .label-qr-box canvas {
            width: 72px !important;
            height: 72px !important;
        }
        .pa-label-code-text {
            margin-top: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.72rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.02em;
            text-align: center;
        }
        .pa-label-info-col {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
            flex: 1;
        }
        .pa-label-brand {
            font-size: 0.85rem;
            font-weight: 800;
            color: #881337;
            text-transform: uppercase;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .pa-label-product {
            font-size: 0.78rem;
            font-weight: 600;
            color: #1e293b;
            text-transform: uppercase;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pa-label-attrs {
            font-size: 0.78rem;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pa-label-mrp {
            font-size: 0.75rem;
            color: #64748b;
            text-decoration: line-through;
            line-height: 1.25;
            margin-top: 2px;
        }
        .pa-label-disc {
            font-size: 0.86rem;
            font-weight: 800;
            color: #15803d;
            line-height: 1.25;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
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
$tagCode     = $labelData['tag_code'] ?? ($labelData['item_code'] ?? 'Product Code');
$qrText      = $qrCodeText ?? ($labelData['qr_payload'] ?? $tagCode);
$badge       = $labelData['section_badge'] ?? 'STANDARD';
$viewOnly    = !empty($viewOnly);
$isFinishedGoods = in_array((string)($labelType ?? ''), ['purchase_fg', 'production'], true)
    || in_array((string)($labelData['stock_tab'] ?? ''), ['finished_goods', 'production'], true);
$finishedGoodsCode = static function (array $data): string {
    $variantId = (int)($data['variant_id'] ?? 0);
    if ($variantId <= 0) {
        return '';
    }

    $currentCode = trim((string)($data['tag_code'] ?? $data['qr_payload'] ?? ''));
    $parsedCode = \App\Services\QrService::parseFinishedGoodsCode($currentCode);
    if ($parsedCode && (int)$parsedCode['variant_id'] === $variantId) {
        return strtoupper($currentCode);
    }

    $code = \App\Services\QrService::generateFinishedGoodsCode($variantId);
    if ($code !== null) {
        return $code;
    }

    return \App\Services\QrService::formatFinishedGoodsCode(
        $variantId,
        $data['main_category_code'] ?? $data['category_code'] ?? null,
        $data['sub_category_code'] ?? $data['subcategory_code'] ?? null,
        $data['sub_sub_category_code'] ?? $data['sub_subcategory_code'] ?? null,
        $data['category_name'] ?? null,
        $data['subcategory_name'] ?? null,
        $data['sub_subcategory_name'] ?? null
    );
};
if ($isFinishedGoods && !empty($labelData['variant_id'])) {
    $tagCode = $finishedGoodsCode($labelData);
    $qrText = $tagCode;
}
$displaySingleProductCode = !empty($labelData['variant_id']) ? (string)(int)$labelData['variant_id'] : $tagCode;
$isProduction = ($labelType === 'production') 
    || (($labelData['section_title'] ?? '') === 'PRODUCTION FINISHED GARMENT')
    || (($labelData['stock_tab'] ?? '') === 'production');
$isPurchaseFinishedGoods = $isFinishedGoods && !$isProduction;
$multiLabelUnit = ($labelType ?? '') === 'product_adding' ? 'bill lines' : 'variants';

// Precalculate variant summary for batch mode
$variantSummary = [];
if (!empty($multiLabels)) {
    foreach ($multiLabels as $_lbl) {
        $sz = $_lbl['variant_size'] ?? '—';
        if (!isset($variantSummary[$sz])) {
            $variantSummary[$sz] = ['sku' => $_lbl['product_code'] ?? '', 'count' => 0];
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
            <?php if (($labelType ?? '') !== 'product_adding'): ?>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.close()">Close</button>
            <?php endif; ?>
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
                        across <strong><?= (int)($variantCount ?? count($multiLabels)) ?> <?= htmlspecialchars($multiLabelUnit) ?></strong>
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
        <?php if (!empty($multiLabels) && !empty($variantSummary) && ($labelType ?? '') !== 'product_adding'): ?>
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
            $isLabelFinishedGoods = ($lbl['stock_tab'] ?? '') === 'finished_goods';
            $isPurchaseFinishedGoodsLabel = $isLabelFinishedGoods && !$isProduction;
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
            $tCode     = $isLabelFinishedGoods && !empty($lbl['variant_id'])
                ? $finishedGoodsCode($lbl)
                : ($lbl['tag_code'] ?? ($lbl['item_code'] ?? 'Product Code'));
            $displayCode = $isProduction && !empty($lbl['variant_id'])
                ? (\App\Services\QrService::generateFinishedGoodsCode((int)$lbl['variant_id']) ?: str_pad((string)(int)$lbl['variant_id'], 3, '0', STR_PAD_LEFT))
                : ($isPurchaseFinishedGoodsLabel
                    ? $tCode
                    : (!empty($lbl['variant_id']) ? (string)(int)$lbl['variant_id'] : $tCode));
            $iQr       = $isProduction && !empty($lbl['variant_id'])
                ? $displayCode
                : ($isLabelFinishedGoods && !empty($lbl['variant_id'])
                    ? $tCode
                    : ($lbl['qr_payload'] ?? $tCode));
            $basePriceVal = (float)($lbl['base_price'] ?? ($lbl['price_mrp'] ?? $pVal));
            $discPriceVal = (float)($lbl['discount_price'] ?? $pVal);
            $vSize     = !empty($lbl['variant_size'])
                ? $lbl['variant_size']
                : ($isPurchaseFinishedGoodsLabel
                    ? ($lblVariantAttributes['Size'] ?? '')
                    : preg_replace('/^SIZE:\s*/i', '', $dVal));
            if (empty($vSize) || $vSize === 'STANDARD') {
                $vSize = 'Free';
            }
            $isProductAddingLabel = ($labelType ?? '') === 'product_adding';
        ?>
            <?php if ($isProductAddingLabel):
                $paBrandTag = strtoupper(trim((string)($lbl['brand_name'] ?? '')));
                if ($paBrandTag === '') {
                    $cParts = preg_split('/\s+/', trim((string)$cName));
                    $paBrandTag = strtoupper($cParts[0] ?? 'GRM');
                }
                $paProductTitle = strtoupper(trim((string)($hTitle ?: $l1Val ?: 'PRODUCT')));
                $paAttrLine = $isPurchaseFinishedGoodsLabel
                    ? strtoupper(trim((string)$vSize)) . ' / ' . strtoupper(trim((string)$l2Val))
                    : strtoupper(trim((string)$l2Val)) . ($dVal !== '' && strtoupper($dVal) !== 'STANDARD' ? ' / ' . strtoupper(trim((string)$dVal)) : '');
                $paMrpVal = (float)($lbl['price_mrp'] ?? $pVal);
                $paSellVal = (float)($lbl['price'] ?? $pVal);
                $paHasDiscount = $paMrpVal > $paSellVal && $paMrpVal > 0;
                $paCodeDisplay = trim((string)($lbl['product_code'] ?? '')) ?: $displayCode;
            ?>
            <div class="label-sticker pa-label-sticker">
                <div class="pa-label-concern-header"><?= htmlspecialchars($cName) ?></div>
                <div class="pa-label-body">
                    <div class="pa-label-qr-col">
                        <div class="pa-label-qr-frame">
                            <div class="label-qr-box qr-container" data-code="<?= htmlspecialchars($iQr) ?>"></div>
                        </div>
                        <div class="pa-label-code-text"><?= htmlspecialchars($paCodeDisplay) ?></div>
                    </div>
                    <div class="pa-label-info-col">
                        <div class="pa-label-brand"><?= htmlspecialchars($paBrandTag) ?></div>
                        <div class="pa-label-product"><?= htmlspecialchars($paProductTitle) ?></div>
                        <div class="pa-label-attrs"><?= htmlspecialchars($paAttrLine) ?></div>
                        <?php if ($paHasDiscount): ?>
                            <div class="pa-label-mrp">MRP: ₹<?= number_format($paMrpVal, 0) ?></div>
                            <div class="pa-label-disc">Disc: ₹<?= number_format($paSellVal, 0) ?></div>
                        <?php else: ?>
                            <div class="pa-label-disc">MRP: ₹<?= number_format($paSellVal, 0) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="label-sticker<?= $isPurchaseFinishedGoodsLabel ? ' finished-goods-label' : '' ?>">
                <div class="label-header-bar">
                    <div class="d-flex align-items-center">
                        <?php if (!$isProduction): ?><span class="concern-check-icon"><i class="bi bi-check-lg"></i></span><?php endif; ?>
                        <span class="concern-name-text"><?= htmlspecialchars($cName) ?></span>
                    </div>
                </div>
                <div class="label-divider"></div>
                <div class="label-body-content">
                    <div class="label-qr-wrapper">
                        <div class="label-qr-box qr-container" data-code="<?= htmlspecialchars($iQr) ?>"></div>
                    </div>
                    <div class="label-details-stack">
                        <?php if (!empty($hTitle) && !$isProduction): ?>
                            <div class="label-item-title"><?= htmlspecialchars($hTitle) ?></div>
                        <?php endif; ?>
                        <?php if ($isPurchaseFinishedGoodsLabel): ?>
                            <div class="label-detail-row">
                                <span class="detail-label"><?= htmlspecialchars($l1Name) ?>:</span>
                                <span class="detail-val"><?= htmlspecialchars($l1Val) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Design:</span>
                                <span class="detail-val text-uppercase"><?= htmlspecialchars($dVal) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label"><?= htmlspecialchars($l2Name) ?>:</span>
                                <span class="detail-val text-uppercase"><?= htmlspecialchars($l2Val) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Size:</span>
                                <span class="detail-val text-uppercase fw-bold"><?= htmlspecialchars($vSize) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Price:</span>
                                <span class="detail-val fw-bold text-success">₹<?= number_format($pVal, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Product Code:</span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($displayCode) ?></span>
                            </div>
                        <?php elseif ($isProduction): ?>
                            <!-- PRODUCTION: Show Size, Base Price, Discount Price, and GRMV Product Code -->
                            <div class="label-detail-row">
                                <span class="detail-label">Size:</span>
                                <span class="detail-val text-uppercase fw-bold"><?= htmlspecialchars($vSize) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Base Price:</span>
                                <span class="detail-val fw-semibold text-dark">₹<?= number_format($basePriceVal, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Discount Price:</span>
                                <span class="detail-val fw-bold text-success">₹<?= number_format($discPriceVal, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">QR Code:</span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($displayCode) ?></span>
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
                                <span class="detail-label"><?= $isLabelFinishedGoods ? 'Product Code:' : 'Code:' ?></span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($displayCode) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <?php 
        $sSize = !empty($labelData['variant_size'])
            ? $labelData['variant_size']
            : ($isPurchaseFinishedGoods
                ? ($variantAttributes['Size'] ?? '')
                : preg_replace('/^SIZE:\s*/i', '', $designVal));
        if (empty($sSize) || $sSize === 'STANDARD') {
            $sSize = 'Free';
        }
        ?>
        <?php for ($i = 0; $i < $qty; $i++): ?>
            <div class="label-sticker<?= $isPurchaseFinishedGoods ? ' finished-goods-label' : '' ?>">
                <!-- Top Header: Concern Name Dynamically -->
                <div class="label-header-bar">
                    <div class="d-flex align-items-center">
                        <?php if (!$isProduction): ?><span class="concern-check-icon"><i class="bi bi-check-lg"></i></span><?php endif; ?>
                        <span class="concern-name-text"><?= htmlspecialchars($concernName) ?></span>
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
                        <?php if (!empty($headerTitle) && !$isProduction): ?>
                            <div class="label-item-title"><?= htmlspecialchars($headerTitle) ?></div>
                        <?php endif; ?>
                        <?php if ($isPurchaseFinishedGoods): ?>
                            <div class="label-detail-row">
                                <span class="detail-label"><?= htmlspecialchars($label1Name) ?>:</span>
                                <span class="detail-val"><?= htmlspecialchars($label1Val) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label"><?= htmlspecialchars($label2Name) ?>:</span>
                                <span class="detail-val text-uppercase"><?= htmlspecialchars($label2Val) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Size:</span>
                                <span class="detail-val text-uppercase fw-bold"><?= htmlspecialchars($sSize) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Price:</span>
                                <span class="detail-val fw-bold text-success">₹<?= number_format($priceVal, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Product Code:</span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($displaySingleProductCode) ?></span>
                            </div>
                        <?php elseif ($isProduction):
                            $sBasePrice = (float)($labelData['base_price'] ?? ($labelData['price_mrp'] ?? $priceVal));
                            $sDiscPrice = (float)($labelData['discount_price'] ?? $priceVal);
                            $sBrandCode = !empty($labelData['variant_id'])
                                ? (\App\Services\QrService::generateFinishedGoodsCode((int)$labelData['variant_id']) ?: str_pad((string)(int)$labelData['variant_id'], 3, '0', STR_PAD_LEFT))
                                : $displaySingleProductCode;
                        ?>
                            <div class="label-detail-row">
                                <span class="detail-label">Size:</span>
                                <span class="detail-val text-uppercase fw-bold"><?= htmlspecialchars($sSize) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Base Price:</span>
                                <span class="detail-val fw-semibold text-dark">₹<?= number_format($sBasePrice, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">Discount Price:</span>
                                <span class="detail-val fw-bold text-success">₹<?= number_format($sDiscPrice, 2) ?></span>
                            </div>
                            <div class="label-detail-row">
                                <span class="detail-label">QR Code:</span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($sBrandCode) ?></span>
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
                                <span class="detail-label"><?= $isFinishedGoods ? 'Product Code:' : 'Code:' ?></span>
                                <span class="detail-val font-monospace text-primary fw-bold"><?= htmlspecialchars($displaySingleProductCode) ?></span>
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
    <?php if (($labelType ?? '') === 'product_adding'): ?>
    const generatedItemCounts = <?= json_encode($generatedItemCounts ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_FORCE_OBJECT) ?>;
    window.__productAddingGeneratedItemCounts = generatedItemCounts;
    if (window.parent !== window) {
        window.parent.postMessage({
            type: 'product-adding-labels-generated',
            itemCounts: generatedItemCounts
        }, window.location.origin);
    } else if (window.opener && !window.opener.closed) {
        window.opener.location.reload();
    }
    <?php endif; ?>
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
