<?php
// app/views/reports/print.php - Clean A4 Print / PDF P&L Report
$summary = $reportData['summary'] ?? [];
$concernRows = $reportData['concern_rows'] ?? [];
$paymentModes = $reportData['payment_modes'] ?? [];
$shifts = $reportData['shifts'] ?? [];
$netPnl = (float)($summary['net_profit_loss'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'P&L Report') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #fff; font-size: 12px; }
        .print-container { max-width: 1000px; margin: 20px auto; padding: 20px; }
        table th { background: #f1f5f9 !important; font-size: 11px; text-transform: uppercase; }
        @media print {
            .no-print { display: none !important; }
            .print-container { max-width: 100%; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
<div class="print-container">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div>
            <h4 class="fw-bold mb-1">Overview &amp; Profit / Loss Report</h4>
            <div class="text-muted">Period: <strong><?= htmlspecialchars($rangeLabel ?? 'All Time') ?></strong></div>
            <div class="text-muted">Generated: <?= htmlspecialchars($generatedAt ?? date('d M Y, h:i A')) ?></div>
        </div>
        <div class="no-print d-flex gap-2">
            <button onclick="window.print()" class="btn btn-sm btn-primary">Print A4 / Save PDF</button>
            <button onclick="window.close()" class="btn btn-sm btn-outline-secondary">Close</button>
        </div>
    </div>

    <h6 class="fw-bold text-uppercase small text-secondary mb-2">Executive Financial Summary</h6>
    <table class="table table-bordered table-sm mb-4">
        <tbody>
            <tr>
                <th>Gross Sales</th><td class="text-end font-monospace">₹<?= number_format((float)($summary['gross_sales'] ?? 0), 2) ?></td>
                <th>Discounts &amp; Coupons</th><td class="text-end font-monospace">₹<?= number_format((float)($summary['discounts_coupons'] ?? 0), 2) ?></td>
            </tr>
            <tr>
                <th>GST Collected</th><td class="text-end font-monospace">₹<?= number_format((float)($summary['gst_collected'] ?? 0), 2) ?></td>
                <th>Net Sales</th><td class="text-end font-monospace fw-bold">₹<?= number_format((float)($summary['net_sales'] ?? 0), 2) ?></td>
            </tr>
            <tr>
                <th>Purchases Cost</th><td class="text-end font-monospace">₹<?= number_format((float)($summary['purchases_cost'] ?? 0), 2) ?></td>
                <th>Operating Expenses</th><td class="text-end font-monospace">₹<?= number_format((float)($summary['operating_expenses'] ?? 0), 2) ?></td>
            </tr>
            <tr>
                <th>Wastage Sales</th><td class="text-end font-monospace">₹<?= number_format((float)($summary['wastage_sales'] ?? 0), 2) ?></td>
                <th>Net Profit / Loss</th><td class="text-end font-monospace fw-bold">₹<?= number_format($netPnl, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <h6 class="fw-bold text-uppercase small text-secondary mb-2">Concern-Wise P&amp;L Breakdown</h6>
    <table class="table table-bordered table-sm mb-4">
        <thead>
            <tr>
                <th>Code</th><th>Concern</th><th class="text-end">Net Sales</th><th class="text-end">GST</th><th class="text-end">Purchases</th><th class="text-end">Expenses</th><th class="text-end">Wastage</th><th class="text-end">Net P&amp;L</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($concernRows as $cr): ?>
                <tr>
                    <td class="font-monospace"><?= htmlspecialchars($cr['code']) ?></td>
                    <td><?= htmlspecialchars($cr['name']) ?></td>
                    <td class="text-end font-monospace">₹<?= number_format($cr['sales_net'], 2) ?></td>
                    <td class="text-end font-monospace">₹<?= number_format($cr['gst_collected'], 2) ?></td>
                    <td class="text-end font-monospace">₹<?= number_format($cr['purchases_net'], 2) ?></td>
                    <td class="text-end font-monospace">₹<?= number_format($cr['expenses'], 2) ?></td>
                    <td class="text-end font-monospace">₹<?= number_format($cr['wastage_sales'], 2) ?></td>
                    <td class="text-end font-monospace fw-bold">₹<?= number_format($cr['net_pnl'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
