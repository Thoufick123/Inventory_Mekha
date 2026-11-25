<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RMA Voucher - <?= htmlspecialchars($return['return_no']) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; color: #1e293b; padding: 20px; line-height: 1.4; }
        .header { border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; }
        .title { font-size: 20px; font-weight: bold; color: #0f172a; margin: 0 0 4px 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #475569; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .summary-box { float: right; width: 280px; margin-top: 16px; border: 1px solid #cbd5e1; padding: 10px; border-radius: 6px; }
        .footer { clear: both; margin-top: 40px; padding-top: 20px; border-top: 1px dashed #cbd5e1; display: flex; justify-content: space-between; font-size: 11px; color: #64748b; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button onclick="window.print()" style="padding: 6px 14px; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Print Voucher</button>
        <button onclick="window.close()" style="padding: 6px 14px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer;">Close</button>
    </div>

    <div class="header">
        <div>
            <h1 class="title"><?= htmlspecialchars($return['concern_name'] ?? 'GRM Inventory') ?></h1>
            <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($return['concern_gstin'] ? 'GSTIN: ' . $return['concern_gstin'] : '') ?></div>
            <div style="font-size: 12px; margin-top: 4px;">Customer: <strong><?= htmlspecialchars($return['customer_name'] ?: 'Retail Walk-in') ?></strong> (<?= htmlspecialchars($return['customer_phone'] ?: '-') ?>)</div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 16px; font-weight: bold; font-family: monospace;"><?= htmlspecialchars($return['return_no']) ?></div>
            <div style="margin-top: 4px;">
                <span class="badge <?= $return['status'] === 'COMPLETED' ? 'badge-completed' : 'badge-cancelled' ?>"><?= htmlspecialchars($return['status']) ?></span>
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Date: <?= htmlspecialchars($return['return_date']) ?></div>
        </div>
    </div>

    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; margin-top: 12px; color: #475569;">RMA Returned & Exchanged Items:</div>
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Item / Variant Style</th>
                <th>Action Taken</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Rate (₹)</th>
                <th class="text-end">Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong><?= $item['direction'] === 'IN' ? 'RETURN' : 'EXCHANGE' ?></strong></td>
                    <td><?= htmlspecialchars($item['variant_name']) ?> (<?= htmlspecialchars($item['product_code']) ?>)</td>
                    <td><?= htmlspecialchars($item['restock_action'] ?: 'Delivered') ?></td>
                    <td class="text-center"><?= number_format((float)$item['qty'], 2) ?></td>
                    <td class="text-end"><?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td class="text-end"><?= number_format((float)$item['total_amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary-box">
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span>Settlement Mode:</span>
            <strong><?= htmlspecialchars($return['settlement_mode'] ?: 'Credit Note') ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px; border-top: 1px solid #e2e8f0; padding-top: 4px;">
            <span>Settlement Amount:</span>
            <strong style="color: #0f172a;">
                <?php if ($return['refund_amount'] > 0): ?>
                    Refund: ₹<?= number_format((float)$return['refund_amount'], 2) ?>
                <?php elseif ($return['extra_charge'] > 0): ?>
                    Collected: ₹<?= number_format((float)$return['extra_charge'], 2) ?>
                <?php else: ?>
                    ₹0.00
                <?php endif; ?>
            </strong>
        </div>
    </div>

    <div class="footer">
        <div>Authorized Signatory: _________________________</div>
        <div>Customer Signature: _________________________</div>
    </div>
</body>
</html>
