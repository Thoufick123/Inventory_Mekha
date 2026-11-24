<div class="container-fluid px-0">
    <!-- Executive Export Intelligence Toolbar (Excel, PDF, Single Day, Monthly, 3 Months, 6 Months) -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4" style="border: 1px solid #e2e8f0 !important;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 44px; height: 44px; background: #ecfdf5; color: #16a34a; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; border: 1px solid #bbf7d0;">
                    <i class="bi bi-file-earmark-excel"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Export Enterprise Reports (Excel & PDF)</h6>
                    <small class="text-muted">Generate consolidated tenant performance, inventory valuation, purchases & revenue as per date period</small>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3">
                <!-- Period Selector -->
                <div class="d-flex align-items-center gap-1">
                    <label class="small text-muted mb-0 fw-semibold" style="font-size: 0.76rem;">Period:</label>
                    <select id="dash_export_period" class="form-select form-select-sm" style="width: 175px; border-radius: 8px; font-size: 0.85rem;">
                        <option value="all" selected>All Time</option>
                        <option value="single_day">Single Day</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="3_months">Particular 3 Months</option>
                        <option value="6_months">Six Months</option>
                        <option value="custom">Custom Date Range</option>
                    </select>
                </div>

                <!-- Single Date Picker (Shown for single_day) -->
                <div id="dash_single_date_box" class="d-none align-items-center gap-1">
                    <input type="date" id="dash_single_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 130px; border-radius: 8px; font-size: 0.82rem;">
                </div>

                <!-- Custom Range Pickers (Shown for custom) -->
                <div id="dash_custom_date_box" class="d-none align-items-center gap-1">
                    <input type="date" id="dash_from_date" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>" style="width: 125px; border-radius: 8px; font-size: 0.82rem;">
                    <span class="text-muted small">to</span>
                    <input type="date" id="dash_to_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 125px; border-radius: 8px; font-size: 0.82rem;">
                </div>

                <!-- Export Dropdown Button -->
                <div class="dropdown">
                    <button type="button" class="btn btn-success btn-sm px-3 fw-bold d-flex align-items-center gap-2 shadow-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px; height: 35px; background: #16a34a; border: none;">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm py-1" style="border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.88rem; min-width: 170px;">
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-success" href="javascript:void(0)" onclick="runDashboardExport('excel')">
                                <i class="bi bi-file-earmark-excel fs-6"></i> Export Excel
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-danger" href="javascript:void(0)" onclick="runDashboardExport('pdf')">
                                <i class="bi bi-file-earmark-pdf fs-6"></i> Export PDF
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-secondary" href="javascript:void(0)" onclick="runDashboardExport('csv')">
                                <i class="bi bi-file-earmark-arrow-down fs-6"></i> CSV
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

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

<script>
document.addEventListener('DOMContentLoaded', function() {
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
