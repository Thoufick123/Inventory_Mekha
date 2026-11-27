<div class="container-fluid px-0">
    <!-- Operations & Ledger Export Intelligence Toolbar -->
    <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('concern_dashboard', 'export') || \App\Core\Auth::can('stocks', 'export')): ?>
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4" style="border: 1px solid #e2e8f0 !important;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 44px; height: 44px; background: #ecfdf5; color: #16a34a; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; border: 1px solid #bbf7d0;">
                    <i class="bi bi-file-earmark-excel"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Export Concern Operations & Ledger (Excel & PDF)</h6>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3">
                <!-- Period Selector -->
                <div class="d-flex align-items-center gap-1">
                    <label class="small text-muted mb-0 fw-semibold" style="font-size: 0.76rem;">Period:</label>
                    <select id="concern_dash_period" class="form-select form-select-sm" style="width: 175px; border-radius: 8px; font-size: 0.85rem;">
                        <option value="all" selected>All Time</option>
                        <option value="single_day">Single Day</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="3_months">Particular 3 Months</option>
                        <option value="6_months">Six Months</option>
                        <option value="custom">Custom Date Range</option>
                    </select>
                </div>

                <!-- Single Date Picker -->
                <div id="concern_single_date_box" class="d-none align-items-center gap-1">
                    <input type="date" id="concern_single_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 130px; border-radius: 8px; font-size: 0.82rem;">
                </div>

                <!-- Custom Range Pickers -->
                <div id="concern_custom_date_box" class="d-none align-items-center gap-1">
                    <input type="date" id="concern_from_date" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>" style="width: 125px; border-radius: 8px; font-size: 0.82rem;">
                    <span class="text-muted small">to</span>
                    <input type="date" id="concern_to_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 125px; border-radius: 8px; font-size: 0.82rem;">
                </div>

                <!-- Export Dropdown Button -->
                <div class="dropdown">
                    <button type="button" class="btn btn-success btn-sm px-3 fw-bold d-flex align-items-center gap-2 shadow-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px; height: 35px; background: #16a34a; border: none;">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm py-1" style="border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.88rem; min-width: 170px;">
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-success" href="javascript:void(0)" onclick="runConcernDashExport('excel')">
                                <i class="bi bi-file-earmark-excel fs-6"></i> Export Excel
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-danger" href="javascript:void(0)" onclick="runConcernDashExport('pdf')">
                                <i class="bi bi-file-earmark-pdf fs-6"></i> Export PDF
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-secondary" href="javascript:void(0)" onclick="runConcernDashExport('csv')">
                                <i class="bi bi-file-earmark-arrow-down fs-6"></i> CSV
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Concern KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-stat d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Total Variants</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($variantsCount) ?></h3>
                </div>
                <div class="stat-icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-tags"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-stat d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Live Stock Units</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($totalStockQty, 2) ?></h3>
                </div>
                <div class="stat-icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-boxes"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-stat d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Inventory Valuation</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0">₹<?= number_format($stockValuation, 2) ?></h3>
                </div>
                <div class="stat-icon-box bg-info bg-opacity-10 text-info">
                    <i class="bi bi-currency-rupee"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-stat d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Liquid Cash / Bank</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0">₹<?= number_format($cashBalance, 2) ?></h3>
                    <small class="text-muted"><?= $totalCustomers ?> Customers &bull; <?= $totalSuppliers ?> Suppliers</small>
                </div>
                <div class="stat-icon-box bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales & Purchases Analytics Graphs -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-graph-up-arrow me-2 text-success"></i> Sales vs Purchases (Last 7 Days)</h6>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Sales: ₹<?= number_format((float)($totalSales ?? 0), 2) ?></span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Purchases: ₹<?= number_format((float)($totalPurchases ?? 0), 2) ?></span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 270px;">
                        <canvas id="concernSalesPurchaseChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-pie-chart-fill me-2 text-primary"></i> Sales by Channel</h6>
                </div>
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div id="concernChannelChartWrap" style="position: relative; height: 270px; width: 100%;">
                        <canvas id="concernChannelChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Stock Movement Ledger -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Stock Ledger Movements</h6>
                    <a href="<?= \App\Core\Controller::url('/m/stock_ledger') ?>" class="btn btn-sm btn-outline-secondary">View Full Ledger</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Variant</th>
                                <th>Module Ref</th>
                                <th>In (+Qty)</th>
                                <th>Out (-Qty)</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLedger)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No stock movements recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentLedger as $sl): ?>
                                    <tr>
                                        <td class="small text-muted font-monospace"><?= date('d M H:i', strtotime($sl['created_at'])) ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($sl['variant_name']) ?></div>
                                            <small class="badge bg-light text-dark border"><?= htmlspecialchars($sl['product_code']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($sl['ref_module']) ?></span>
                                            <?php if ($sl['note']): ?>
                                                <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($sl['note']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sl['qty_in'] > 0): ?>
                                                <span class="text-success fw-bold">+<?= number_format($sl['qty_in'], 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sl['qty_out'] > 0): ?>
                                                <span class="text-danger fw-bold">-<?= number_format($sl['qty_out'], 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= htmlspecialchars($sl['user_name'] ?? 'System') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Low Stock Threshold Alerts</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php if (empty($lowStocks)): ?>
                            <li class="list-group-item text-success text-center py-4">
                                <i class="bi bi-check-circle fs-3 d-block mb-1"></i>
                                All inventory levels are healthy!
                            </li>
                        <?php else: ?>
                            <?php foreach ($lowStocks as $ls): ?>
                                <li class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($ls['variant_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($ls['product_code']) ?> &bull; <?= htmlspecialchars($ls['size'] ?: 'Standard') ?></small>
                                    </div>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1 fs-6">
                                        <?= number_format($ls['qty'], 2) ?> <?= htmlspecialchars($ls['weight_unit']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const concernDailyChart = <?= json_encode($dailyChartData ?? ['labels' => [], 'sales' => [], 'purchases' => []], JSON_UNESCAPED_UNICODE) ?>;
const concernChannelRows = <?= json_encode($salesByChannel ?? [], JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart !== 'undefined') {
        const spCanvas = document.getElementById('concernSalesPurchaseChart');
        if (spCanvas) {
            new Chart(spCanvas, {
                type: 'bar',
                data: {
                    labels: concernDailyChart.labels || [],
                    datasets: [
                        {
                            label: 'Sales (₹)',
                            data: concernDailyChart.sales || [],
                            backgroundColor: '#10b981',
                            borderRadius: 6,
                            maxBarThickness: 32
                        },
                        {
                            label: 'Purchases (₹)',
                            data: concernDailyChart.purchases || [],
                            backgroundColor: '#4f46e5',
                            borderRadius: 6,
                            maxBarThickness: 32
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const val = Number(ctx.parsed.y || 0);
                                    return ctx.dataset.label + ': ₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + Number(value).toLocaleString('en-IN');
                                }
                            },
                            grid: { color: '#f1f5f9' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        const chWrap = document.getElementById('concernChannelChartWrap');
        const chCanvas = document.getElementById('concernChannelChart');
        const chTotals = (concernChannelRows || []).map(r => parseFloat(r.total || 0) || 0);
        const chSum = chTotals.reduce((a, b) => a + b, 0);

        if (chCanvas && concernChannelRows.length > 0 && chSum > 0) {
            const palette = ['#2563eb', '#10b981', '#f59e0b', '#06b6d4', '#8b5cf6', '#ef4444'];
            new Chart(chCanvas, {
                type: 'doughnut',
                data: {
                    labels: concernChannelRows.map(r => r.label || 'Direct Customer'),
                    datasets: [{
                        data: chTotals,
                        backgroundColor: concernChannelRows.map((_, i) => palette[i % palette.length]),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const val = Number(ctx.parsed || 0);
                                    const pct = chSum > 0 ? ((val / chSum) * 100).toFixed(1) : '0.0';
                                    return ctx.label + ': ₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        } else if (chWrap) {
            chWrap.innerHTML = '<div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted small"><i class="bi bi-receipt fs-2 mb-2 opacity-50"></i>No sales recorded yet.</div>';
        }
    }

    const periodSelect = document.getElementById('concern_dash_period');
    const singleDateBox = document.getElementById('concern_single_date_box');
    const customDateBox = document.getElementById('concern_custom_date_box');

    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            const p = this.value;
            if (p === 'single_day') {
                if (singleDateBox) { singleDateBox.classList.remove('d-none'); singleDateBox.classList.add('d-flex'); }
                if (customDateBox) { customDateBox.classList.add('d-none'); customDateBox.classList.remove('d-flex'); }
            } else if (p === 'custom') {
                if (customDateBox) { customDateBox.classList.remove('d-none'); customDateBox.classList.add('d-flex'); }
                if (singleDateBox) { singleDateBox.classList.add('d-none'); singleDateBox.classList.remove('d-flex'); }
            } else {
                if (singleDateBox) { singleDateBox.classList.add('d-none'); singleDateBox.classList.remove('d-flex'); }
                if (customDateBox) { customDateBox.classList.add('d-none'); customDateBox.classList.remove('d-flex'); }
            }
        });
    }
});

function runConcernDashExport(format) {
    const period = document.getElementById('concern_dash_period').value;
    const singleDate = document.getElementById('concern_single_date') ? document.getElementById('concern_single_date').value : '';
    const fromDate = document.getElementById('concern_from_date') ? document.getElementById('concern_from_date').value : '';
    const toDate = document.getElementById('concern_to_date') ? document.getElementById('concern_to_date').value : '';

    const url = '<?= \App\Core\Controller::url('/dashboard/export') ?>' +
                '?format=' + encodeURIComponent(format) +
                '&period=' + encodeURIComponent(period) +
                '&single_date=' + encodeURIComponent(singleDate) +
                '&from_date=' + encodeURIComponent(fromDate) +
                '&to_date=' + encodeURIComponent(toDate);

    if (format === 'pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}
</script>
