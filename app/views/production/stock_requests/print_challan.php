<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dispatch Challan - <?= htmlspecialchars($request['dispatch_challan_no'] ?: $request['request_no']) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 13px; color: #1e293b; padding: 20px; line-height: 1.4; }
        .header { border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; }
        .title { font-size: 20px; font-weight: bold; color: #0f172a; margin: 0 0 4px 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; background: #e0f2fe; color: #0369a1; }
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
        <button onclick="window.print()" style="padding: 6px 14px; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Print Challan</button>
        <button onclick="window.close()" style="padding: 6px 14px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer;">Close</button>
    </div>

    <div class="header">
        <div style="display: flex; align-items: flex-start; gap: 12px;">
            <?php if (!empty($request['concern_logo'])): ?>
                <img src="<?= htmlspecialchars(\App\Services\CloudinaryService::url($request['concern_logo'])) ?>" alt="<?= htmlspecialchars($request['concern_name'] ?? 'Logo') ?>" style="max-height: 48px; max-width: 130px; object-fit: contain;">
            <?php endif; ?>
            <div>
                <h1 class="title"><?= htmlspecialchars($request['concern_name'] ?? 'GRM Inventory') ?></h1>
                <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($request['concern_gstin'] ? 'GSTIN: ' . $request['concern_gstin'] : '') ?></div>
                <div style="font-size: 12px; margin-top: 6px;">
                    <strong>DISPATCH DELIVERY CHALLAN</strong>
                </div>
                <div style="font-size: 12px; color: #475569; margin-top: 4px;">
                    From: <strong><?= htmlspecialchars($request['from_location_type']) ?> #<?= (int)$request['from_location_id'] ?></strong> &rarr;
                    To: <strong><?= htmlspecialchars($request['to_location_type']) ?> #<?= (int)$request['to_location_id'] ?></strong>
                </div>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 16px; font-weight: bold; font-family: monospace; color: #0f172a;"><?= htmlspecialchars($request['dispatch_challan_no'] ?: 'CHALLAN PENDING') ?></div>
            <div style="margin-top: 4px;">
                <span class="badge">Requisition #<?= htmlspecialchars($request['request_no']) ?></span>
            </div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Date: <?= htmlspecialchars($request['dispatched_at'] ?: date('Y-m-d H:i')) ?></div>
        </div>
    </div>

    <div style="font-size: 12px; font-weight: 600; text-transform: uppercase; margin-top: 12px; color: #475569;">Dispatched Garment Line Items (In-Transit):</div>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">#</th>
                <th style="width: 50%;">Variant Description</th>
                <th style="width: 20%;" class="text-center">Requested Qty</th>
                <th style="width: 20%;" class="text-center">Dispatched Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 1;
            $totalDispatched = 0;
            foreach ($items as $item): 
                $totalDispatched += (float)$item['dispatched_qty'];
            ?>
                <tr>
                    <td><?= $count++ ?></td>
                    <td>
                        <strong><?= htmlspecialchars($item['variant_name']) ?></strong>
                        <div style="font-size: 11px; color: #64748b; font-family: monospace;"><?= htmlspecialchars($item['product_code']) ?> (<?= htmlspecialchars($item['size'] ?? 'Standard') ?>)</div>
                    </td>
                    <td class="text-center"><?= number_format((float)$item['requested_qty'], 2) ?></td>
                    <td class="text-center" style="font-weight: bold; font-size: 14px;"><?= number_format((float)$item['dispatched_qty'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="background: #f8fafc; font-weight: bold;">
                <td colspan="3" class="text-end">Total Dispatched Items:</td>
                <td class="text-center"><?= number_format($totalDispatched, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 20px; font-size: 11px; color: #64748b;">
        <strong>Notes:</strong> <?= nl2br(htmlspecialchars($request['notes'] ?: 'Stock dispatched under formal movement rules.')) ?>
    </div>

    <div class="footer">
        <div>Dispatcher Signature: _________________________</div>
        <div>Transporter Signature: _________________________</div>
        <div>Receiver Verification: _________________________</div>
    </div>
</body>
</html>
