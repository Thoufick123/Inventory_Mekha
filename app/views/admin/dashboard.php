<div class="container-fluid px-0">
    <?php
    $exportUrl = \App\Core\Controller::url('/admin/reports/export');
    $currentModule = 'admin_reports';
    $prefix = 'admin_dash_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Super Admin Header Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-stat d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Active Concerns</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($concernsCount) ?></h3>
                    <small class="text-success"><i class="bi bi-shield-check"></i> Multi-Tenant Isolated</small>
                </div>
                <div class="stat-icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-buildings"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-stat d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Enterprise Assets</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($assetsCount) ?></h3>
                    <small class="text-primary">₹<?= number_format($assetsValuation, 2) ?> Book Value</small>
                </div>
                <div class="stat-icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-building-gear"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-stat d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Total Users</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($usersCount) ?></h3>
                    <small class="text-info">Platform & Tenant Staff</small>
                </div>
                <div class="stat-icon-box bg-info bg-opacity-10 text-info">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-stat d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Architecture Status</span>
                    <h4 class="fw-bold text-dark mt-1 mb-0">Production</h4>
                    <small class="text-muted">Strict Tenant Scoping</small>
                </div>
                <div class="stat-icon-box bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-cpu"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Concern-Wise Analytics Graphs -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-line me-2 text-primary"></i> Concern-Wise Financial Overview</h6>
                        <small class="text-muted">Sales, Purchases &amp; Stock Valuation across active concerns</small>
                    </div>
                    <span class="badge bg-light text-secondary border">All Time (INR)</span>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 290px;">
                        <canvas id="concernFinancialChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-pie-chart me-2 text-success"></i> Stock Units by Concern</h6>
                        <small class="text-muted">Live inventory unit distribution</small>
                    </div>
                </div>
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div id="concernStockChartWrap" style="position: relative; height: 290px; width: 100%;">
                        <canvas id="concernStockChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Concerns Summary Table -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-building me-2 text-primary"></i> Registered Concerns Overview</h6>
                    <a href="<?= \App\Core\Controller::url('/admin/concerns') ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Concern
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Concern Name</th>
                                <th>Active Modules</th>
                                <th>Staff Users</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($concerns as $c): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($c['code']) ?></span></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($c['gstin'] ?: 'No GSTIN') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                            <i class="bi bi-check2-circle me-1"></i> <?= (int)$c['active_modules_count'] ?> Active
                                        </span>
                                    </td>
                                    <td><?= (int)$c['users_count'] ?> Staff</td>
                                    <td>
                                        <span class="badge <?= $c['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= ucfirst($c['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= \App\Core\Controller::url('/admin/concerns') ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-gear"></i> Configure
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const concernChartRows = <?= json_encode($concernChartData ?? [], JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart !== 'undefined' && Array.isArray(concernChartRows) && concernChartRows.length > 0) {
        const labels = concernChartRows.map(r => r.name || r.code || 'Concern');
        const salesData = concernChartRows.map(r => parseFloat(r.total_sales || 0) || 0);
        const purchaseData = concernChartRows.map(r => parseFloat(r.total_purchases || 0) || 0);
        const valuationData = concernChartRows.map(r => parseFloat(r.stock_valuation || 0) || 0);
        const stockQtyData = concernChartRows.map(r => parseFloat(r.total_stock_qty || 0) || 0);

        const finCanvas = document.getElementById('concernFinancialChart');
        if (finCanvas) {
            new Chart(finCanvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Sales (₹)',
                            data: salesData,
                            backgroundColor: '#10b981',
                            borderRadius: 6,
                            maxBarThickness: 36
                        },
                        {
                            label: 'Purchases (₹)',
                            data: purchaseData,
                            backgroundColor: '#4f46e5',
                            borderRadius: 6,
                            maxBarThickness: 36
                        },
                        {
                            label: 'Stock Valuation (₹)',
                            data: valuationData,
                            backgroundColor: '#0ea5e9',
                            borderRadius: 6,
                            maxBarThickness: 36
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

        const totalUnits = stockQtyData.reduce((acc, v) => acc + v, 0);
        const stockWrap = document.getElementById('concernStockChartWrap');
        const stockCanvas = document.getElementById('concernStockChart');
        if (stockCanvas && totalUnits > 0) {
            const palette = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'];
            new Chart(stockCanvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: stockQtyData,
                        backgroundColor: labels.map((_, i) => palette[i % palette.length]),
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
                                    const pct = totalUnits > 0 ? ((val / totalUnits) * 100).toFixed(1) : '0.0';
                                    return ctx.label + ': ' + val.toLocaleString('en-IN') + ' units (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        } else if (stockWrap) {
            stockWrap.innerHTML = '<div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted small"><i class="bi bi-boxes fs-2 mb-2 opacity-50"></i>No stock units recorded yet.</div>';
        }
    }

    const periodSelect = document.getElementById('dash_export_period');
    const singleDateBox = document.getElementById('dash_single_date_box');
    const customDateBox = document.getElementById('dash_custom_date_box');

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

function runDashboardExport(format) {
    const period = document.getElementById('dash_export_period').value;
    const singleDate = document.getElementById('dash_single_date') ? document.getElementById('dash_single_date').value : '';
    const fromDate = document.getElementById('dash_from_date') ? document.getElementById('dash_from_date').value : '';
    const toDate = document.getElementById('dash_to_date') ? document.getElementById('dash_to_date').value : '';

    const url = '<?= \App\Core\Controller::url('/admin/reports/export') ?>' +
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
