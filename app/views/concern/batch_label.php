<?php
// app/views/concern/batch_label.php
// Warehouse Carton QR Sticker & Barcode Receiving Tag (Image 3)

$batchNo = htmlspecialchars($batch['batch_no'] ?? 'BAT-20261113-0002');
$puCode = htmlspecialchars($batch['production_unit_code'] ?? 'PU-01');
$puName = htmlspecialchars($batch['production_unit_name'] ?? 'Kadampadi');
$whName = htmlspecialchars($batch['warehouse_name'] ?? 'wareouse (WH-01)');
$progNo = htmlspecialchars($batch['program_no'] ?? 'PRG-20261113-0001');
$productName = htmlspecialchars($batch['program_name'] ?? $batch['variant_name'] ?? 'KIDS - BABY - DRESS');
$qty = (int)($batch['batch_qty'] ?? 70);
$boxes = (int)($batch['total_boxes'] ?? 1);
$sku = htmlspecialchars($batch['product_code'] ?? 'GRM-STY-0001-L');
$size = htmlspecialchars($batch['size'] ?? 'L');
$fabric = htmlspecialchars($batch['fabric_details'] ?? 'GREEN / BIRDS');
$status = strtoupper($batch['status'] ?? 'PREPARING');
$preparedBy = htmlspecialchars($batch['packed_by_name'] ?? 'System Administrator');
$dateStr = date('d M Y, h:i A', strtotime($batch['created_at'] ?? '2026-11-13 13:28:00'));

// QR Code URL using high-res standard endpoint
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=0&data=' . urlencode($batchNo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Carton QR Sticker - <?= $batchNo ?></title>
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap-icons.min.css') ?>">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 1.5rem;
            color: #1e293b;
        }
        .action-toolbar {
            max-width: 680px;
            margin: 0 auto 1.5rem auto;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .carton-sticker-card {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 12px;
            padding: 1.75rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .barcode-container {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
            background: #f8fafc;
            margin: 1.25rem 0;
        }
        .barcode-lines {
            display: inline-block;
            height: 44px;
            letter-spacing: 2px;
        }
        .custom-table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 1rem;
            border: 1px solid #0f172a;
        }
        .custom-table th {
            background-color: #1e293b;
            color: #ffffff;
            padding: 0.5rem 0.75rem;
            font-size: 0.78rem;
            text-transform: uppercase;
            font-weight: 700;
        }
        .custom-table td {
            padding: 0.65rem 0.75rem;
            border: 1px solid #e2e8f0;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        @media print {
            body {
                background: none;
                padding: 0;
            }
            .action-toolbar {
                display: none !important;
            }
            .carton-sticker-card {
                box-shadow: none;
                border: 2px solid #000;
                margin: 0;
                width: 100%;
                max-width: 100%;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Buttons -->
    <div class="action-toolbar">
        <button type="button" class="btn btn-primary fw-semibold px-3 shadow-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Carton QR Sticker
        </button>
        <a href="<?= \App\Core\Controller::url('/production/batches/challan/' . $batch['id']) ?>" target="_blank" class="btn btn-success fw-bold px-3 shadow-sm">
            <i class="bi bi-receipt me-1"></i> Delivery Challan & Invoice
        </a>
        <a href="<?= \App\Core\Controller::url('/production/outward') ?>" class="btn btn-outline-secondary px-3 fw-semibold bg-white shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Outward
        </a>
    </div>

    <!-- Carton Sticker Card (Image 3) -->
    <div class="carton-sticker-card">
        <!-- Sticker Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
            <div>
                <h4 class="fw-black mb-1 text-dark tracking-tight" style="font-weight: 900; letter-spacing: -0.02em;">
                    GRM WAREHOUSE INWARD
                </h4>
                <div class="badge bg-dark text-white text-uppercase px-2.5 py-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                    PRODUCTION BATCH RECEIVING TAG
                </div>
            </div>
            <div class="text-end">
                <small class="text-muted d-block mb-1" style="font-size: 0.72rem;">Origin &rarr; Target Warehouse:</small>
                <div class="d-inline-flex align-items-center gap-1 font-monospace">
                    <span class="badge text-dark fw-bold px-2 py-1" style="background:#fef08a; font-size:0.75rem;"><?= $puCode ?></span>
                    <span class="text-muted">&rarr;</span>
                    <span class="badge text-white fw-bold px-2 py-1" style="background:#0284c7; font-size:0.75rem;"><?= $whName ?></span>
                </div>
            </div>
        </div>

        <!-- Upper Details & Large Batch QR Code -->
        <div class="row align-items-center mb-2">
            <!-- Left Info -->
            <div class="col-7">
                <div class="mb-2">
                    <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                        PRODUCTION BATCH NUMBER:
                    </small>
                    <div class="fw-bold font-monospace text-dark fs-5" style="letter-spacing: -0.01em;">
                        <?= $batchNo ?>
                    </div>
                </div>

                <div class="mb-2">
                    <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                        ORIGIN PRODUCTION FACILITY:
                    </small>
                    <div class="fw-semibold text-dark small d-flex align-items-center gap-1">
                        <i class="bi bi-geo-alt-fill text-warning"></i> <?= $puName ?>
                    </div>
                </div>

                <div class="mb-3">
                    <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                        PRODUCT STYLE & PROGRAM:
                    </small>
                    <div class="fw-bold text-primary fs-6 mb-1">
                        <?= $productName ?>
                    </div>
                    <span class="badge bg-secondary font-monospace" style="font-size:0.7rem;">
                        PRG: <?= $progNo ?>
                    </span>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem;">TOTAL QUANTITY:</small>
                        <div class="fw-bold text-success fs-5"><?= $qty ?> Pcs</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem;">CARTON COUNT:</small>
                        <div class="fw-bold text-dark fs-6"><?= $boxes ?> Box(es)</div>
                    </div>
                </div>
            </div>

            <!-- Right Large QR Code -->
            <div class="col-5 text-center">
                <div class="d-inline-block p-2 border rounded-3 bg-white shadow-sm">
                    <img src="<?= $qrUrl ?>" alt="Batch QR Code" style="width: 140px; height: 140px; display: block;" class="img-fluid">
                    <small class="d-block font-monospace text-muted mt-1" style="font-size: 0.7rem;"><?= $batchNo ?></small>
                </div>
            </div>
        </div>

        <!-- Horizontal Barcode (Code128 SVG generator) -->
        <div class="barcode-container">
            <svg id="barcodeSvg" style="max-height: 48px; width: 85%;"></svg>
            <div class="font-monospace text-dark fw-bold mt-1" style="font-size: 0.78rem; letter-spacing: 0.15em;">
                * <?= $batchNo ?> *
            </div>
        </div>

        <!-- Specification / Items Table -->
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Fabric</th>
                    <th style="width: 35%;">Color & Design</th>
                    <th style="width: 25%;">Product Code</th>
                    <th style="width: 10%;" class="text-center">Size</th>
                    <th style="width: 15%;" class="text-end">Qty (Pcs)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($batchItems)): ?>
                    <?php foreach ($batchItems as $bi): ?>
                        <tr>
                            <td class="text-center py-2">
                                <div class="d-inline-flex p-1.5 bg-light rounded border text-muted">
                                    <i class="bi bi-box fs-6"></i>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($bi['variant_name'] ?? $productName) ?></div>
                                <small class="badge bg-light text-secondary border px-1" style="font-size: 0.68rem;"><?= htmlspecialchars($bi['color'] ?? 'Standard') ?></small>
                            </td>
                            <td class="font-monospace fw-semibold text-dark">
                                <?= htmlspecialchars($bi['product_code'] ?? '-') ?>
                            </td>
                            <td class="text-center fw-bold text-dark">
                                <?= htmlspecialchars($bi['size'] ?? 'Standard') ?>
                            </td>
                            <td class="text-end fw-bold text-success fs-6">
                                <?= (int)$bi['qty'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td class="text-center py-2">
                            <div class="d-inline-flex p-2 bg-light rounded border text-muted">
                                <i class="bi bi-image fs-5"></i>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">GREEN</div>
                            <small class="badge bg-light text-secondary border px-1" style="font-size: 0.68rem;">BIRDS</small>
                            <div class="text-muted small mt-0.5" style="font-size: 0.72rem;">Total: <?= $qty ?> pcs</div>
                        </td>
                        <td class="font-monospace fw-semibold text-dark">
                            <?= $sku ?>
                        </td>
                        <td class="text-center fw-bold text-dark">
                            <?= $size ?>
                        </td>
                        <td class="text-end fw-bold text-success fs-6">
                            <?= $qty ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Sticker Footer -->
        <div class="d-flex justify-content-between align-items-center pt-3 mt-3 border-top small text-muted">
            <div>
                Prepared By: <strong class="text-dark"><?= $preparedBy ?></strong>
            </div>
            <div>
                Date: <strong class="text-dark"><?= $dateStr ?></strong>
            </div>
            <div>
                <?php if ($status === 'PREPARING'): ?>
                    <span class="badge text-dark px-2.5 py-1 fw-bold" style="background:#fef08a; font-size: 0.72rem;">STATUS: PREPARING</span>
                <?php elseif ($status === 'APPROVED'): ?>
                    <span class="badge bg-success text-white px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">STATUS: APPROVED</span>
                <?php elseif ($status === 'RECEIVED'): ?>
                    <span class="badge text-white px-2.5 py-1 fw-bold" style="background:#06b6d4; font-size: 0.72rem;">STATUS: RECEIVED</span>
                <?php else: ?>
                    <span class="badge bg-secondary px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">STATUS: <?= $status ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Script to render crisp SVG Barcode -->
    <script>
    (function drawBarcode() {
        var svg = document.getElementById('barcodeSvg');
        var text = '<?= $batchNo ?>';
        var bars = '';
        var width = 360;
        var height = 44;
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

        // Pattern generator for barcode bars
        var seed = 0;
        for (var i = 0; i < text.length; i++) {
            seed = (seed * 31 + text.charCodeAt(i)) & 0xffffffff;
        }
        var x = 20;
        while (x < width - 20) {
            seed = (seed * 1103515245 + 12345) & 0x7fffffff;
            var barWidth = (seed % 3) + 1;
            var isBar = (seed % 2 === 0);
            if (isBar && (x + barWidth < width - 20)) {
                bars += '<rect x="' + x + '" y="0" width="' + barWidth + '" height="' + height + '" fill="#000" />';
            }
            x += barWidth + 1;
        }
        svg.innerHTML = bars;
    })();
    </script>
</body>
</html>
