<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shop Damage Memo - <?= htmlspecialchars($damage['damage_no']) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; color: #1e293b; padding: 20px; line-height: 1.4; }
        .header { border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; }
        .title { font-size: 20px; font-weight: bold; color: #0f172a; margin: 0 0 4px 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-success { background: #dcfce7; color: #166534; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #475569; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .footer { clear: both; margin-top: 50px; padding-top: 20px; border-top: 1px dashed #cbd5e1; display: flex; justify-content: space-between; font-size: 11px; color: #64748b; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button onclick="window.print()" style="padding: 6px 14px; background: #dc2626; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Print Shop Damage Memo</button>
        <button onclick="window.close()" style="padding: 6px 14px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer;">Close</button>
    </div>

    <div class="header">
        <div>
            <h1 class="title"><?= htmlspecialchars($damage['concern_name'] ?? 'GRM Inventory') ?></h1>
            <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($damage['concern_gstin'] ? 'GSTIN: ' . $damage['concern_gstin'] : '') ?></div>
            <div style="font-size: 12px; margin-top: 6px;">
                <strong>RETAIL SHOP DAMAGE MEMORANDUM</strong>
            </div>
            <div style="font-size: 11px; color: #64748b;">Shop Floor: <?= htmlspecialchars($damage['location'] ?: 'Retail Store') ?></div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 16px; font-weight: bold; font-family: monospace; color: #dc2626;"><?= htmlspecialchars($damage['damage_no']) ?></div>
            <div style="margin-top: 4px;">
                <span class="badge <?= $damage['status'] === 'RESOLVED' ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars($damage['status']) ?> (<?= htmlspecialchars($damage['severity']) ?>)</span>
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Date: <?= htmlspecialchars($damage['created_at']) ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Variant Style Description</th>
                <th class="text-center">Severity</th>
                <th class="text-center">Damaged Qty</th>
                <th class="text-end">Retail Price (₹)</th>
                <th class="text-end">Est. Loss (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($damage['variant_name']) ?></strong>
                    <div style="font-size: 11px; font-family: monospace; color: #64748b;"><?= htmlspecialchars($damage['sku_code']) ?> (<?= htmlspecialchars($damage['size'] ?? 'Standard') ?>)</div>
                </td>
                <td class="text-center"><strong><?= htmlspecialchars($damage['severity']) ?></strong></td>
                <td class="text-center" style="font-size: 14px; font-weight: bold; color: #dc2626;"><?= number_format((float)$damage['qty'], 2) ?></td>
                <td class="text-end">₹<?= number_format((float)$damage['unit_price'], 2) ?></td>
                <td class="text-end" style="font-weight: bold;">₹<?= number_format((float)($damage['loss_amount'] ?: ($damage['qty'] * $damage['unit_price'])), 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 16px; border: 1px solid #cbd5e1; padding: 10px; border-radius: 6px; background: #f8fafc;">
        <div><strong>Damage Defect Reason:</strong> <?= htmlspecialchars($damage['resolution_reason'] ?: 'Customer / handling damage') ?></div>
        <?php if (!empty($damage['resolution_notes'])): ?>
            <div style="margin-top: 4px;"><strong>Resolution Notes:</strong> <?= nl2br(htmlspecialchars($damage['resolution_notes'])) ?></div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <div>Shop Floor Staff: _________________________</div>
        <div>Store Manager Authorization: _________________________</div>
    </div>
</body>
</html>
