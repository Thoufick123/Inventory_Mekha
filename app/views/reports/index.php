<?php
// app/views/reports/index.php - Overview & P&L Report Dashboard
$summary = $reportData['summary'] ?? [];
$concernRows = $reportData['concern_rows'] ?? [];
$paymentModes = $reportData['payment_modes'] ?? [];
$dailyTrend = $reportData['daily_trend'] ?? [];
$expenseCategories = $reportData['expense_categories'] ?? [];
$shifts = $reportData['shifts'] ?? [];

$netPnl = (float)($summary['net_profit_loss'] ?? 0);
$isProfit = $netPnl >= 0;

$queryArr = [
    'period'    => $period ?? 'month',
    'from_date' => $fromDate ?? '',
    'to_date'   => $toDate ?? '',
];
if (!empty($selectedConcernIds)) {
    $queryArr['concern_id'] = implode(',', $selectedConcernIds);
}
if (!empty($selectedUnitId)) {
    $queryArr['unit_id'] = (int)$selectedUnitId;
}
$qs = http_build_query(array_filter($queryArr, static fn($v) => $v !== '' && $v !== null));
$printHref = \App\Core\Controller::url($reportPrintUrl . ($qs ? '?' . $qs : ''));
$csvHref = \App\Core\Controller::url($reportExportUrl . ($qs ? '?' . $qs . '&format=csv' : '?format=csv'));
$excelHref = \App\Core\Controller::url($reportExportUrl . ($qs ? '?' . $qs . '&format=excel' : '?format=excel'));
?>
<style>
.report-table-format {
    border-collapse: collapse;
    font-size: 0.84rem;
}
.report-table-format thead th {
    background-color: #f1f5f9 !important;
    color: #475569 !important;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.6rem 0.75rem;
    border-bottom: 2px solid #cbd5e1 !important;
    white-space: nowrap;
}
.report-table-format tbody td, .report-table-format tfoot td {
    padding: 0.55rem 0.75rem;
    border-color: #e2e8f0 !important;
    vertical-align: middle;
    white-space: nowrap;
}
</style>

<div class="container-fluid px-2 pt-1 pb-3">
    <!-- Filter & Action Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url($reportBaseUrl) ?>" class="row g-2 align-items-end" id="reportFilterForm">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold text-secondary mb-1">Date Preset</label>
                    <select name="period" id="reportPeriodSelect" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                        <option value="today" <?= ($period === 'today') ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= ($period === 'week') ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= ($period === 'month') ? 'selected' : '' ?>>This Month</option>
                        <option value="last_30_days" <?= ($period === 'last_30_days') ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="all" <?= ($period === 'all') ? 'selected' : '' ?>>All Time</option>
                        <option value="custom" <?= ($period === 'custom') ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 report-custom-date <?= ($period === 'custom') ? '' : 'd-none' ?>">
                    <label class="form-label small fw-semibold text-secondary mb-1">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate ?? '') ?>">
                </div>
                <div class="col-md-2 col-sm-6 report-custom-date <?= ($period === 'custom') ? '' : 'd-none' ?>">
                    <label class="form-label small fw-semibold text-secondary mb-1">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate ?? '') ?>">
                </div>
                <?php if (!empty($isSuperAdmin) && count($allConcerns) > 1): ?>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold text-secondary mb-1">Concern</label>
                    <select name="concern_id" class="form-select form-select-sm">
                        <option value="">All Concerns</option>
                        <?php foreach ($allConcerns as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (count($selectedConcernIds) === 1 && (int)$selectedConcernIds[0] === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (!empty($units)): ?>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-semibold text-secondary mb-1">Production Unit</label>
                    <select name="unit_id" class="form-select form-select-sm">
                        <option value="">All Units</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= ((int)($selectedUnitId ?? 0) === (int)$u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3">
                        <i class="bi bi-funnel me-1"></i> Apply
                    </button>
                </div>
                <div class="col ms-auto d-flex justify-content-end align-items-center gap-2 flex-wrap">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1.5 px-2.5">
                        <i class="bi bi-calendar-check me-1"></i><?= htmlspecialchars($rangeLabel ?? 'This Month') ?>
                    </span>
                    <a href="<?= $printHref ?>" target="_blank" class="btn btn-sm btn-outline-dark fw-semibold text-nowrap">
                        <i class="bi bi-printer me-1"></i> Print A4
                    </a>
                    <a href="<?= $csvHref ?>" class="btn btn-sm btn-outline-success fw-semibold text-nowrap">
                        <i class="bi bi-filetype-csv me-1"></i> CSV
                    </a>
                    <a href="<?= $excelHref ?>" class="btn btn-sm btn-success fw-semibold text-nowrap">
                        <i class="bi bi-file-earmark-excel me-1"></i> Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive P&L KPI Cards -->
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Net Sales Revenue (<?= (int)($summary['invoice_count'] ?? 0) ?> Bills)</small>
                <div class="fs-4 fw-bold text-success mt-1 font-monospace">₹<?= number_format((float)($summary['net_sales'] ?? 0), 2) ?></div>
                <div class="small text-muted mt-1">
                    Std: ₹<?= number_format((float)($summary['standard_sales'] ?? 0), 0) ?> • Direct: ₹<?= number_format((float)($summary['direct_sales'] ?? 0), 0) ?>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Purchases &amp; Expenses</small>
                <div class="fs-4 fw-bold text-danger mt-1 font-monospace">₹<?= number_format((float)($summary['purchases_cost'] ?? 0) + (float)($summary['operating_expenses'] ?? 0), 2) ?></div>
                <div class="small text-muted mt-1">
                    Purch: ₹<?= number_format((float)($summary['purchases_cost'] ?? 0), 0) ?> • Exp: ₹<?= number_format((float)($summary['operating_expenses'] ?? 0), 0) ?>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Net Profit / Loss (P&amp;L)</small>
                <div class="fs-4 fw-bold <?= $isProfit ? 'text-primary' : 'text-danger' ?> mt-1 font-monospace">
                    <?= $isProfit ? '+' : '' ?>₹<?= number_format($netPnl, 2) ?>
                </div>
                <div class="small text-muted mt-1">
                    Wastage: ₹<?= number_format((float)($summary['wastage_sales'] ?? 0), 0) ?> • GST: ₹<?= number_format((float)($summary['gst_collected'] ?? 0), 0) ?>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Inventory Valuation &amp; Discounts</small>
                <div class="fs-4 fw-bold text-dark mt-1 font-monospace">₹<?= number_format((float)($summary['stock_valuation'] ?? 0), 2) ?></div>
                <div class="small text-muted mt-1">
                    Stock: <?= number_format((float)($summary['stock_qty'] ?? 0), 0) ?> pcs • Disc: ₹<?= number_format((float)($summary['discounts_coupons'] ?? 0), 0) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row (Lightweight Client-Side Canvas/SVG) -->
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-2.5 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark small text-uppercase"><i class="bi bi-graph-up text-primary me-1"></i> Daily Sales vs Expenses Trend</h6>
                    <span class="small text-muted">Last <?= count($dailyTrend) ?> active day(s)</span>
                </div>
                <div class="card-body p-3">
                    <canvas id="pnlTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-2.5 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark small text-uppercase"><i class="bi bi-pie-chart text-success me-1"></i> Payment Mode &amp; Channel Split</h6>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <div class="small fw-semibold text-secondary mb-1">B2C vs B2B Billing</div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>B2C (<?= (int)($summary['b2c_count'] ?? 0) ?>): <strong>₹<?= number_format((float)($summary['b2c_sales'] ?? 0), 2) ?></strong></span>
                            <span>B2B (<?= (int)($summary['b2b_count'] ?? 0) ?>): <strong>₹<?= number_format((float)($summary['b2b_sales'] ?? 0), 2) ?></strong></span>
                        </div>
                    </div>
                    <div class="small fw-semibold text-secondary mb-2">Payment Modes</div>
                    <?php if (empty($paymentModes)): ?>
                        <div class="text-muted small">No sales recorded for this period.</div>
                    <?php else: ?>
                        <?php
                        $pmTotal = array_sum(array_map(static fn($m) => (float)$m['amount'], $paymentModes)) ?: 1;
                        foreach ($paymentModes as $pm):
                            $pct = round(((float)$pm['amount'] / $pmTotal) * 100, 1);
                        ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-0.5">
                                    <span class="text-uppercase fw-semibold"><?= htmlspecialchars($pm['mode']) ?> (<?= (int)$pm['cnt'] ?>)</span>
                                    <span class="font-monospace">₹<?= number_format((float)$pm['amount'], 2) ?> (<?= $pct ?>%)</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Concern-Wise P&L Breakdown Table -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white py-2.5 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-table text-primary me-2"></i>P&amp;L Breakdown by Concern</h6>
            <input type="search" id="pnlConcernSearch" class="form-control form-control-sm" style="max-width: 240px;" placeholder="Filter concern...">
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle mb-0 report-table-format" id="pnlConcernTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Concern</th>
                        <th class="text-center">Invoices</th>
                        <th class="text-end">Net Sales</th>
                        <th class="text-end">GST</th>
                        <th class="text-end">Discounts</th>
                        <th class="text-end">Purchases</th>
                        <th class="text-end">Expenses</th>
                        <th class="text-end">Wastage</th>
                        <th class="text-end">Net P&amp;L</th>
                        <th class="text-end">Stock Val</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($concernRows)): ?>
                        <tr><td colspan="11" class="text-center py-4 text-muted">No concern data found for this scope.</td></tr>
                    <?php else: ?>
                        <?php foreach ($concernRows as $cr): ?>
                            <tr>
                                <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($cr['code']) ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($cr['name']) ?></td>
                                <td class="text-center"><?= (int)$cr['invoice_count'] ?></td>
                                <td class="text-end font-monospace text-success fw-semibold">₹<?= number_format($cr['sales_net'], 2) ?></td>
                                <td class="text-end font-monospace">₹<?= number_format($cr['gst_collected'], 2) ?></td>
                                <td class="text-end font-monospace text-muted">₹<?= number_format($cr['discounts'], 2) ?></td>
                                <td class="text-end font-monospace text-danger">₹<?= number_format($cr['purchases_net'], 2) ?></td>
                                <td class="text-end font-monospace text-danger">₹<?= number_format($cr['expenses'], 2) ?></td>
                                <td class="text-end font-monospace text-info">₹<?= number_format($cr['wastage_sales'], 2) ?></td>
                                <td class="text-end font-monospace fw-bold <?= $cr['net_pnl'] >= 0 ? 'text-primary' : 'text-danger' ?>">
                                    ₹<?= number_format($cr['net_pnl'], 2) ?>
                                </td>
                                <td class="text-end font-monospace">₹<?= number_format($cr['stock_valuation'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Expense Categories & Cashier Shifts -->
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-2.5 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-wallet2 text-danger me-2"></i>Top Expense Categories</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0 report-table-format">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-center">Count</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenseCategories)): ?>
                                <tr><td colspan="3" class="text-center py-3 text-muted">No expenses recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($expenseCategories as $ec): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($ec['category']) ?></td>
                                        <td class="text-center"><?= (int)$ec['cnt'] ?></td>
                                        <td class="text-end font-monospace text-danger fw-semibold">₹<?= number_format((float)$ec['total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-2.5 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cash-stack text-success me-2"></i>Cashier Shifts Reconciliation</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0 report-table-format">
                        <thead>
                            <tr>
                                <th>Shift #</th>
                                <th>Cashier</th>
                                <th class="text-end">Opening</th>
                                <th class="text-end">Expected</th>
                                <th class="text-end">Counted</th>
                                <th class="text-end">Variance</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($shifts)): ?>
                                <tr><td colspan="7" class="text-center py-3 text-muted">No cashier shifts in this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($shifts as $sh): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold"><?= htmlspecialchars($sh['shift_no']) ?></td>
                                        <td><?= htmlspecialchars($sh['cashier_name']) ?></td>
                                        <td class="text-end font-monospace">₹<?= number_format((float)$sh['starting_float'], 2) ?></td>
                                        <td class="text-end font-monospace">₹<?= number_format((float)($sh['expected_closing_float'] ?? 0), 2) ?></td>
                                        <td class="text-end font-monospace">₹<?= number_format((float)($sh['closing_float'] ?? 0), 2) ?></td>
                                        <td class="text-end font-monospace <?= ((float)($sh['variance'] ?? 0)) < 0 ? 'text-danger' : 'text-success' ?>">
                                            ₹<?= number_format((float)($sh['variance'] ?? 0), 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $sh['status'] === 'open' ? 'bg-success' : 'bg-secondary' ?>"><?= strtoupper($sh['status']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCustomDates(val) {
    document.querySelectorAll('.report-custom-date').forEach(function(el) {
        el.classList.toggle('d-none', val !== 'custom');
    });
}

(function() {
    const searchInp = document.getElementById('pnlConcernSearch');
    if (searchInp) {
        searchInp.addEventListener('input', function() {
            const q = searchInp.value.trim().toLowerCase();
            document.querySelectorAll('#pnlConcernTable tbody tr').forEach(function(tr) {
                tr.style.display = (!q || tr.textContent.toLowerCase().includes(q)) ? '' : 'none';
            });
        });
    }

    const canvas = document.getElementById('pnlTrendChart');
    const trendData = <?= json_encode($dailyTrend) ?>;
    if (canvas && canvas.getContext) {
        const ctx = canvas.getContext('2d');
        const w = canvas.parentElement.clientWidth - 20;
        const h = 150;
        canvas.width = Math.max(300, w);
        canvas.height = h;
        ctx.clearRect(0, 0, canvas.width, h);

        if (!trendData.length) {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '13px sans-serif';
            ctx.fillText('No daily sales or expense transactions in this date range.', 16, 75);
            return;
        }

        const maxVal = Math.max(1, ...trendData.map(d => Math.max(d.sales || 0, d.expenses || 0)));
        const padL = 10, padB = 24, padT = 12;
        const chartH = h - padB - padT;
        const groupW = (canvas.width - padL * 2) / trendData.length;
        const barW = Math.max(6, Math.min(20, groupW * 0.35));

        trendData.forEach(function(d, i) {
            const x = padL + i * groupW + groupW * 0.15;
            const sH = ((d.sales || 0) / maxVal) * chartH;
            const eH = ((d.expenses || 0) / maxVal) * chartH;

            ctx.fillStyle = '#2563eb';
            ctx.fillRect(x, padT + chartH - sH, barW, sH);

            ctx.fillStyle = '#ef4444';
            ctx.fillRect(x + barW + 2, padT + chartH - eH, barW, eH);

            ctx.fillStyle = '#64748b';
            ctx.font = '10px monospace';
            ctx.fillText((d.date || '').slice(5), x, h - 6);
        });
    }
})();
</script>
