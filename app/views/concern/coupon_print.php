<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupon Voucher - <?= htmlspecialchars($coupon['code'] ?? '') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 24px; }
        .voucher { max-width: 420px; margin: 0 auto; background: #fff; border: 2px dashed #2563eb; border-radius: 14px; padding: 24px; text-align: center; box-shadow: 0 8px 20px rgba(15,23,42,.08); }
        .logo { max-height: 52px; max-width: 140px; object-fit: contain; margin-bottom: 8px; }
        .concern { font-size: 18px; font-weight: 800; margin: 0; }
        .meta { font-size: 12px; color: #64748b; margin-top: 4px; }
        .offer { margin: 18px 0; padding: 14px; background: #eff6ff; border-radius: 10px; }
        .offer-val { font-size: 28px; font-weight: 900; color: #1d4ed8; }
        .code-box { font-family: monospace; font-size: 22px; font-weight: 800; letter-spacing: 2px; background: #0f172a; color: #fff; padding: 10px 16px; border-radius: 8px; display: inline-block; margin: 10px 0; }
        .rules { font-size: 12px; color: #475569; text-align: left; border-top: 1px dashed #cbd5e1; padding-top: 12px; margin-top: 14px; line-height: 1.6; }
        .actions { text-align: center; margin-bottom: 16px; }
        .btn { padding: 8px 18px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-light { background: #e2e8f0; color: #0f172a; margin-left: 6px; }
        @media print { .actions { display: none; } body { background: #fff; padding: 0; } .voucher { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <button class="btn btn-primary" onclick="window.print()">Print Coupon</button>
        <button class="btn btn-light" onclick="window.close()">Close</button>
    </div>
    <div class="voucher">
        <?php if (!empty($coupon['concern_logo'])): ?>
            <img src="<?= htmlspecialchars(\App\Services\CloudinaryService::url($coupon['concern_logo'])) ?>" alt="Logo" class="logo">
        <?php endif; ?>
        <h2 class="concern"><?= htmlspecialchars($coupon['concern_name'] ?: 'GRM Inventory') ?></h2>
        <?php if (!empty($coupon['concern_phone']) || !empty($coupon['concern_gstin'])): ?>
            <div class="meta">
                <?= !empty($coupon['concern_phone']) ? 'Ph: ' . htmlspecialchars($coupon['concern_phone']) : '' ?>
                <?= !empty($coupon['concern_gstin']) ? ' &bull; GSTIN: ' . htmlspecialchars($coupon['concern_gstin']) : '' ?>
            </div>
        <?php endif; ?>

        <div class="offer">
            <div style="font-size: 12px; text-transform: uppercase; font-weight: 700; color: #3b82f6;"><?= htmlspecialchars($coupon['title'] ?? 'Special Offer') ?></div>
            <div class="offer-val">
                <?= ($coupon['discount_type'] ?? 'FIXED') === 'PERCENT'
                    ? number_format((float) ($coupon['discount_value'] ?? 0), 0) . '% OFF'
                    : '₹' . number_format((float) ($coupon['discount_value'] ?? 0), 2) . ' OFF' ?>
            </div>
            <div class="code-box"><?= htmlspecialchars($coupon['code'] ?? '') ?></div>
        </div>

        <div class="rules">
            <?php if (!empty($coupon['issued_invoice_no'])): ?>
                <div><strong>Issued On Bill:</strong> #<?= htmlspecialchars($coupon['issued_invoice_no']) ?></div>
            <?php endif; ?>
            <div><strong>Min. Bill Amount:</strong> ₹<?= number_format((float) ($coupon['min_redemption_amount'] ?? 0), 2) ?></div>
            <div><strong>Valid Until:</strong> <?= !empty($coupon['expires_at']) ? date('d M Y', strtotime($coupon['expires_at'])) : 'No Expiry' ?></div>
            <div><strong>Status:</strong> <?= htmlspecialchars($coupon['status'] ?? 'ACTIVE') ?></div>
        </div>
    </div>
</body>
</html>
