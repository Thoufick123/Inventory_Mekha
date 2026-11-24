<?php
// app/views/concern/batch_challan.php
// Delivery Challan, Outward Gate Pass & Tax Invoice for Finished Production Batches

$batchNo      = htmlspecialchars($batch['batch_no'] ?? 'BAT-20261124-0001');
$billNo       = htmlspecialchars($batch['bill_no'] ?? 'BILL-20261124-0001');
$outwardNo    = htmlspecialchars($outward['outward_no'] ?? ($batch['outward_no'] ?? 'OUT-PENDING'));
$gatePassNo   = htmlspecialchars($outward['gate_pass_no'] ?? ($batch['gate_pass_no'] ?? 'GP-PENDING'));
$programNo    = htmlspecialchars($batch['program_no'] ?? 'PRG-GEN');
$programName  = htmlspecialchars($batch['program_name'] ?? 'Finished Garments');
$totalQty     = (float)($batch['batch_qty'] ?? 0);
$totalBoxes   = max(1, (int)($batch['total_boxes'] ?? 1));
$status       = strtoupper($batch['status'] ?? 'PREPARING');

// Concern Details (Source)
$srcName      = htmlspecialchars($concern['name'] ?? 'GRM Garments Pvt Ltd');
$srcCode      = htmlspecialchars($concern['code'] ?? 'GRM');
$srcAddress   = htmlspecialchars($concern['address'] ?? '12, Textile City, Tirupur, Tamil Nadu');
$srcGstin     = htmlspecialchars($concern['gstin'] ?? '33AABCG1234F1Z5');
$srcPhone     = htmlspecialchars($concern['phone'] ?? '+91 98421 23456');

// Destination Concern / Warehouse
$destName     = htmlspecialchars($destConcern['name'] ?? ($outward['dest_concern_name'] ?? ($batch['warehouse_name'] ?? 'Main Store')));
$destCode     = htmlspecialchars($destConcern['code'] ?? ($outward['dest_concern_code'] ?? 'WH'));
$destAddress  = htmlspecialchars($destConcern['address'] ?? 'Central Distribution Hub');
$destGstin    = htmlspecialchars($destConcern['gstin'] ?? '');

// Logistics
$vehicleNo    = htmlspecialchars($outward['vehicle_number'] ?? ($batch['vehicle_number'] ?? 'TN-38-BK-4421'));
$driverName   = htmlspecialchars($outward['driver_name'] ?? ($batch['driver_name'] ?? 'Murugan'));
$driverPhone  = htmlspecialchars($outward['driver_contact'] ?? ($batch['driver_contact'] ?? '9842109876'));
$transporter  = htmlspecialchars($outward['transporter_name'] ?? ($batch['transporter_name'] ?? 'Express Logistics'));
$packedBy     = htmlspecialchars($batch['packed_by_name'] ?? 'System Administrator');
$dispatchDate = date('d M Y, h:i A', strtotime($outward['dispatched_at'] ?? ($batch['created_at'] ?? 'now')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan & Invoice - <?= $batchNo ?></title>
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap-icons.min.css') ?>">
    <style>
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            padding: 1.5rem;
            font-size: 13px;
        }
        .action-bar {
            max-width: 900px;
            margin: 0 auto 1.5rem auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .invoice-card {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .header-title {
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.85rem 1rem;
            height: 100%;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.25rem;
            border: 1px solid #cbd5e1;
        }
        .table-custom th {
            background: #0f172a;
            color: #ffffff;
            padding: 8px 10px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            border: 1px solid #0f172a;
        }
        .table-custom td {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .table-custom tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .total-row {
            background: #f1f5f9 !important;
            font-weight: bold;
            border-top: 2px solid #0f172a;
        }
        .signature-box {
            border: 1px dashed #94a3b8;
            border-radius: 6px;
            padding: 2.5rem 1rem 0.5rem 1rem;
            text-align: center;
            font-size: 11px;
            color: #475569;
            background: #fcfcfd;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .invoice-card {
                border: 2px solid #000;
                box-shadow: none;
                margin: 0;
                width: 100%;
                max-width: 100%;
                border-radius: 0;
                padding: 1.5rem;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Action Toolbar (Hidden during print) -->
    <div class="action-bar">
        <div class="d-flex align-items-center gap-2">
            <a href="<?= \App\Core\Controller::url('/production/outward?tab=batch') ?>" class="btn btn-outline-secondary btn-sm fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to Outward
            </a>
            <a href="<?= \App\Core\Controller::url('/production/batches/label/' . $batch['id']) ?>" class="btn btn-outline-primary btn-sm fw-semibold">
                <i class="bi bi-qr-code-scan me-1"></i> Carton QR Sticker
            </a>
            <?php if (!empty($outward['id'])): ?>
            <a href="<?= \App\Core\Controller::url('/production/outward') ?>" class="btn btn-outline-info btn-sm fw-semibold">
                <i class="bi bi-truck me-1"></i> Outward Manifests
            </a>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-success btn-sm fw-bold px-3 shadow-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Delivery Challan & Invoice
            </button>
        </div>
    </div>

    <!-- Printable Invoice & Challan Document -->
    <div class="invoice-card">
        <!-- Document Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
            <div>
                <h3 class="header-title mb-1"><?= $srcName ?></h3>
                <div class="text-secondary small mb-1"><?= $srcAddress ?></div>
                <div class="small fw-semibold text-dark">
                    <span>GSTIN: <?= $srcGstin ?></span> &bull; 
                    <span>Phone: <?= $srcPhone ?></span>
                </div>
            </div>
            <div class="text-end">
                <div class="badge bg-dark text-white px-2.5 py-1 text-uppercase fw-bold mb-1" style="font-size:0.75rem; letter-spacing:0.05em;">
                    DELIVERY CHALLAN & TAX INVOICE
                </div>
                <div class="fw-bold fs-5 text-primary font-monospace"><?= $outwardNo ?></div>
                <div class="small text-muted font-monospace">Gate Pass: <strong><?= $gatePassNo ?></strong></div>
            </div>
        </div>

        <!-- 3-Column Meta Details -->
        <div class="row g-3 mb-3">
            <!-- Col 1: Dispatch From -->
            <div class="col-md-4">
                <div class="meta-box">
                    <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                        <i class="bi bi-geo-alt me-1 text-primary"></i> DISPATCH FROM (ORIGIN)
                    </div>
                    <div class="fw-bold text-dark"><?= $srcName ?> (<?= $srcCode ?>)</div>
                    <div class="small text-secondary"><?= $srcAddress ?></div>
                    <div class="small text-muted mt-1">Location: <strong>Main Store (Finished Goods)</strong></div>
                </div>
            </div>

            <!-- Col 2: Destination Concern & Warehouse -->
            <div class="col-md-4">
                <div class="meta-box">
                    <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                        <i class="bi bi-building me-1 text-success"></i> DESTINATION CONCERN & STORE
                    </div>
                    <div class="fw-bold text-success fs-6"><?= $destName ?></div>
                    <?php if ($destGstin): ?>
                        <div class="small text-secondary">GSTIN: <?= $destGstin ?></div>
                    <?php endif; ?>
                    <div class="small text-secondary"><?= $destAddress ?></div>
                    <div class="small text-muted mt-1">Warehouse: <strong><?= htmlspecialchars($batch['warehouse_name'] ?? 'wareouse') ?></strong></div>
                </div>
            </div>

            <!-- Col 3: Logistics & Vehicle -->
            <div class="col-md-4">
                <div class="meta-box">
                    <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                        <i class="bi bi-truck me-1 text-info"></i> LOGISTICS & VEHICLE DETAILS
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Vehicle No:</span>
                        <span class="fw-bold font-monospace text-dark"><?= $vehicleNo ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Driver:</span>
                        <span class="fw-semibold text-dark"><?= $driverName ?> (<?= $driverPhone ?>)</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Carrier:</span>
                        <span class="text-secondary"><?= $transporter ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Dispatch Date:</span>
                        <span class="text-dark small fw-semibold"><?= $dispatchDate ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Reference Bar -->
        <div class="bg-light p-2.5 rounded border mb-3 d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <span class="text-muted small text-uppercase fw-bold me-1">Production Batch:</span>
                <span class="fw-bold font-monospace text-dark fs-6"><?= $batchNo ?></span>
                <span class="mx-2 text-muted">&bull;</span>
                <span class="text-muted small text-uppercase fw-bold me-1">Program:</span>
                <span class="fw-bold text-primary font-monospace"><?= $programNo ?></span>
                <span class="text-secondary">(<?= $programName ?>)</span>
            </div>
            <div>
                <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                    Bill Ref: <strong class="text-dark font-monospace"><?= $billNo ?></strong>
                </span>
                <span class="badge bg-success-subtle text-success border px-2 py-1 ms-1">
                    Status: <strong class="text-success"><?= $status ?></strong>
                </span>
            </div>
        </div>

        <!-- Variant Line Items Table -->
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 32%;">VARIANT DESCRIPTION</th>
                    <th style="width: 18%;">SKU CODE</th>
                    <th style="width: 10%;" class="text-center">SIZE</th>
                    <th style="width: 10%;" class="text-end">QTY (PCS)</th>
                    <th style="width: 12%;" class="text-end">UNIT RATE (₹)</th>
                    <th style="width: 13%;" class="text-end">NET AMOUNT (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sno = 1;
                $sumQty = 0;
                $sumTaxable = 0.00;
                $sumGst = 0.00;
                $sumTotal = 0.00;

                if (!empty($items)):
                    foreach ($items as $it):
                        $iQty = (float)($it['qty'] ?? 0);
                        $iPrice = (float)($it['unit_price'] ?? 700.00);
                        $iTotal = (float)($it['total_amount'] ?: ($iQty * $iPrice));
                        $iTaxable = (float)($it['taxable_value'] ?: round($iTotal / 1.05, 2));
                        $iGst = (float)($it['gst_amount'] ?: round($iTotal - $iTaxable, 2));

                        $sumQty += $iQty;
                        $sumTaxable += $iTaxable;
                        $sumGst += $iGst;
                        $sumTotal += $iTotal;
                ?>
                    <tr>
                        <td class="text-center"><?= $sno++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($it['variant_name'] ?? ($it['name'] ?? $programName)) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($it['color'] ? ($it['color'] . ' / ') : '') ?><?= htmlspecialchars($it['size'] ?? '') ?></small>
                        </td>
                        <td>
                            <span class="font-monospace fw-semibold text-dark" style="font-size:0.8rem;"><?= htmlspecialchars($it['sku_code'] ?? '-') ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-2 py-0.5"><?= htmlspecialchars($it['size'] ?? 'L') ?></span>
                        </td>
                        <td class="text-end fw-bold font-monospace fs-6 text-dark"><?= number_format($iQty, 0) ?></td>
                        <td class="text-end">₹<?= number_format($iPrice, 2) ?></td>
                        <td class="text-end fw-bold text-dark">₹<?= number_format($iTotal, 2) ?></td>
                    </tr>
                <?php
                    endforeach;
                else:
                    $sumQty = $totalQty;
                    $sumTotal = (float)($batch['total_amount'] ?: ($totalQty * 700));
                    $sumTaxable = (float)($batch['taxable_value'] ?: round($sumTotal / 1.05, 2));
                    $sumGst = (float)($batch['gst_amount'] ?: round($sumTotal - $sumTaxable, 2));
                ?>
                    <tr>
                        <td class="text-center">1</td>
                        <td>
                            <div class="fw-bold text-dark"><?= $programName ?></div>
                            <small class="text-muted"><?= htmlspecialchars($batch['variant_name'] ?? 'Standard') ?></small>
                        </td>
                        <td><span class="font-monospace fw-semibold"><?= htmlspecialchars($batch['sku_code'] ?? 'KBD-001') ?></span></td>
                        <td class="text-center"><?= htmlspecialchars($batch['size'] ?? 'L') ?></td>
                        <td class="text-end fw-bold font-monospace fs-6"><?= number_format($totalQty, 0) ?></td>
                        <td class="text-end">₹<?= number_format((float)($batch['price'] ?? 700), 2) ?></td>
                        <td class="text-end fw-bold">₹<?= number_format($sumTotal, 2) ?></td>
                    </tr>
                <?php endif; ?>

                <!-- Totals Row -->
                <tr class="total-row">
                    <td colspan="4" class="text-end text-uppercase" style="font-size: 0.75rem;">
                        Total Quantities & Packing:
                        <span class="badge bg-dark text-white ms-2"><?= $totalBoxes ?> Box(es)</span>
                    </td>
                    <td class="text-end font-monospace fs-6 text-success fw-bold"><?= number_format($sumQty, 0) ?> pcs</td>
                    <td class="text-end text-muted small">Taxable Value:</td>
                    <td class="text-end font-monospace fw-bold">₹<?= number_format($sumTaxable, 2) ?></td>
                </tr>
                <tr style="background:#f8fafc; font-weight:bold;">
                    <td colspan="5" class="border-0"></td>
                    <td class="text-end text-muted small border-start">GST (5% Inclusive):</td>
                    <td class="text-end font-monospace text-primary border-end">₹<?= number_format($sumGst, 2) ?></td>
                </tr>
                <tr style="background:#e0f2fe; font-weight:bold; font-size:14px;">
                    <td colspan="5" class="border-0 text-muted small text-start align-middle">
                        Goods dispatched strictly for finished stock transfer under internal delivery challan.
                    </td>
                    <td class="text-end text-dark border-start">GRAND TOTAL:</td>
                    <td class="text-end font-monospace text-success border-end fs-6">₹<?= number_format($sumTotal, 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Signatures & Acknowledgment -->
        <div class="row g-3 mt-4 pt-2">
            <div class="col-4">
                <div class="signature-box">
                    <div class="fw-bold text-dark"><?= $packedBy ?></div>
                    <div class="text-muted" style="font-size: 0.68rem;">PACKED & PREPARED BY</div>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    <div class="fw-bold text-dark"><?= $driverName ?> (<?= $vehicleNo ?>)</div>
                    <div class="text-muted" style="font-size: 0.68rem;">CARRIER / TRANSPORTER SIGNATURE</div>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    <div class="fw-bold text-dark"><?= $destName ?></div>
                    <div class="text-muted" style="font-size: 0.68rem;">CONSIGNEE STORE VERIFIED & RECEIVED</div>
                </div>
            </div>
        </div>

        <div class="text-center text-muted small mt-4 pt-2 border-top" style="font-size: 0.72rem;">
            This is a computer-generated delivery challan and outward tax invoice valid across all company concerns and tax authorities.
        </div>
    </div>

</body>
</html>
