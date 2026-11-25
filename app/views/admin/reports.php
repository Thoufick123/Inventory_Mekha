<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <div class="text-secondary small mb-1">
                <a href="<?= \App\Core\Controller::url('/admin/dashboard') ?>" class="text-decoration-none text-muted">Super Admin</a>
                <span class="mx-1">/</span>
                <span class="text-dark fw-semibold">Consolidated Reports</span>
            </div>
            <h4 class="fw-bold mb-1 text-dark">Enterprise Multi-Concern Reports & Analytics</h4>
            <p class="text-secondary small mb-0">Cross-tenant inventory valuation, revenue volume, and procurement metrics. Download consolidated or separated reports by concern.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                <i class="bi bi-calendar-check me-1"></i> <?= htmlspecialchars($rangeLabel ?? 'All Time') ?>
            </span>
            <?php if (!empty($selectedConcernIds)): ?>
                <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
                    <i class="bi bi-filter-circle me-1"></i> Filtered: <?= count($selectedConcernIds) ?> Concern(s)
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Concern Scope & Filter Toolbar -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3" style="border: 1px solid #e2e8f0 !important;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label for="admin_concern_filter" class="fw-bold text-dark small mb-0 d-flex align-items-center gap-1">
                    <i class="bi bi-buildings text-primary"></i> Concern Scope:
                </label>
                <select id="admin_concern_filter" class="form-select form-select-sm" style="min-width: 270px; border-radius: 8px; font-weight: 500;" onchange="onConcernFilterChange(this.value)">
                    <option value="" <?= empty($selectedConcernIds) ? 'selected' : '' ?>>-- All Concerns (Consolidated) --</option>
                    <?php foreach ($allConcerns as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (count($selectedConcernIds) === 1 && in_array($c['id'], $selectedConcernIds)) ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($c['code']) ?>] <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($selectedConcernIds)): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearConcernFilter()" title="Clear Concern Filter">
                        <i class="bi bi-x-circle me-1"></i> Show All
                    </button>
                <?php endif; ?>
            </div>
            <div class="text-secondary small d-flex align-items-center gap-2">
                <i class="bi bi-info-circle text-primary"></i>
                <span>Use the table checkboxes below to select <strong>any 2 or separated concerns</strong> to download separated or combined reports.</span>
            </div>
        </div>
    </div>

    <!-- Export Intelligence Toolbar Card -->
    <?php
    $exportTitle = 'Export Enterprise Reports (Excel, PDF, CSV)';
    $exportSubtitle = !empty($selectedConcernIds) 
        ? ('Scoped to ' . count($selectedConcernIds) . ' selected concern(s) - valuation, purchases & revenue as per period')
        : 'Generate consolidated performance, valuation, purchases & revenue across all registered concerns';
    
    $concernQueryStr = !empty($selectedConcernIds) ? ('?concern_id=' . implode(',', $selectedConcernIds)) : '';
    $exportUrl = \App\Core\Controller::url('/admin/reports/export' . $concernQueryStr);
    $prefix = 'admin_rep_exp';
    $defaultPeriod = $period ?? 'all';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Floating Batch Export Action Bar for Selected Concerns -->
    <div id="batch_export_bar" class="card border-0 shadow-sm rounded-4 p-3 mb-3 d-none" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #ffffff;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <div style="width: 38px; height: 38px; background: rgba(255,255,255,0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="bi bi-check2-square text-white"></i>
                </div>
                <div>
                    <div class="fw-bold fs-6 text-white">
                        <span id="selected_concerns_count">0</span> Concerns Selected
                    </div>
                    <small id="selected_concerns_names" class="text-white-50" style="font-size: 0.8rem;"></small>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-white-50 small me-1">Export Mode:</span>
                <!-- Combined Export -->
                <button type="button" class="btn btn-sm btn-light fw-bold text-primary shadow-sm" onclick="exportSelectedCombined('excel')">
                    <i class="bi bi-file-earmark-excel text-success me-1"></i> Combined Report (Excel)
                </button>
                <button type="button" class="btn btn-sm btn-light fw-bold text-primary shadow-sm" onclick="exportSelectedCombined('pdf')">
                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i> Combined (PDF)
                </button>
                <button type="button" class="btn btn-sm btn-light fw-bold text-primary shadow-sm" onclick="exportSelectedCombined('csv')">
                    <i class="bi bi-file-earmark-arrow-down text-secondary me-1"></i> CSV
                </button>
                <!-- Separated Reports (1 file per concern) -->
                <button type="button" class="btn btn-sm btn-warning fw-bold text-dark shadow-sm" onclick="downloadSeparatedReports('excel')" title="Downloads a separated Excel report file for each selected concern">
                    <i class="bi bi-folder-symlink me-1"></i> Download Separated Reports (Excel)
                </button>
                <button type="button" class="btn btn-sm btn-outline-light" onclick="clearAllSelections()" title="Deselect All">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Metrics Table -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border: 1px solid #e2e8f0 !important;">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-line me-2 text-primary"></i> Tenant Performance Breakdown</h6>
                <span class="badge bg-light text-dark border"><?= count($concernStats) ?> Shown</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllVisibleConcerns()">
                    <i class="bi bi-check-all me-1"></i> Select All
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllSelections()">
                    Clear
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="width: 44px; text-align: center;">
                            <input type="checkbox" id="master_concern_check" class="form-check-input" onchange="toggleMasterCheck(this)" title="Select / Deselect All">
                        </th>
                        <th>Concern</th>
                        <th>Variants Mastered</th>
                        <th>Stock Units In Hand</th>
                        <th>Live Inventory Valuation</th>
                        <th>Gross Purchases in Period</th>
                        <th>Gross Invoiced Sales in Period</th>
                        <th style="text-align: right; width: 160px;">Action / Separated Export</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandStockQty = 0;
                    $grandValuation = 0;
                    $grandPurchases = 0;
                    $grandSales = 0;

                    if (empty($concernStats)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No concerns found matching the selected filter.
                            </td>
                        </tr>
                    <?php else:
                        foreach ($concernStats as $cs): 
                            $grandStockQty += (float)$cs['total_stock_qty'];
                            $grandValuation += (float)$cs['stock_valuation'];
                            $grandPurchases += (float)$cs['total_purchases'];
                            $grandSales += (float)$cs['total_sales'];
                        ?>
                            <tr id="row_concern_<?= $cs['id'] ?>">
                                <td style="text-align: center;">
                                    <input type="checkbox" class="form-check-input concern-row-check" 
                                           value="<?= $cs['id'] ?>" 
                                           data-name="<?= htmlspecialchars($cs['name']) ?>" 
                                           data-code="<?= htmlspecialchars($cs['code']) ?>"
                                           onchange="onRowCheckChange()">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($cs['name']) ?></div>
                                    <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($cs['code']) ?></span>
                                </td>
                                <td><?= number_format($cs['total_variants']) ?> items</td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace">
                                        <?= number_format((float)$cs['total_stock_qty'], 2) ?>
                                    </span>
                                </td>
                                <td class="fw-semibold text-dark font-monospace">₹<?= number_format((float)$cs['stock_valuation'], 2) ?></td>
                                <td class="text-secondary font-monospace">₹<?= number_format((float)$cs['total_purchases'], 2) ?></td>
                                <td class="fw-bold text-success font-monospace">₹<?= number_format((float)$cs['total_sales'], 2) ?></td>
                                <td style="text-align: right;">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle d-flex align-items-center gap-1 ms-auto" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; border-radius: 7px; padding: 4px 10px;">
                                            <i class="bi bi-download"></i> Download Report
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.84rem; border-radius: 8px;">
                                            <li>
                                                <a class="dropdown-item py-2 d-flex align-items-center gap-2 text-success fw-semibold" href="javascript:void(0)" onclick="downloadSingleConcern(<?= $cs['id'] ?>, 'excel')">
                                                    <i class="bi bi-file-earmark-excel fs-6"></i> Excel Report (.xls)
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger fw-semibold" href="javascript:void(0)" onclick="downloadSingleConcern(<?= $cs['id'] ?>, 'pdf')">
                                                    <i class="bi bi-file-earmark-pdf fs-6"></i> PDF Report (.pdf)
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2 d-flex align-items-center gap-2 text-secondary fw-semibold" href="javascript:void(0)" onclick="downloadSingleConcern(<?= $cs['id'] ?>, 'csv')">
                                                    <i class="bi bi-file-earmark-arrow-down fs-6"></i> CSV Data (.csv)
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach;
                    endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="2">
                            <?= !empty($selectedConcernIds) ? 'TOTAL (SELECTED ' . count($concernStats) . ' CONCERNS)' : 'TOTAL CONSOLIDATED (' . count($concernStats) . ' CONCERNS)' ?>
                        </td>
                        <td>-</td>
                        <td class="font-monospace"><?= number_format($grandStockQty, 2) ?></td>
                        <td class="text-primary font-monospace">₹<?= number_format($grandValuation, 2) ?></td>
                        <td class="font-monospace">₹<?= number_format($grandPurchases, 2) ?></td>
                        <td class="text-success font-monospace">₹<?= number_format($grandSales, 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
// Get current period parameters from the export toolbar card
function getActiveDateParams() {
    const prefix = '<?= $prefix ?>';
    const periodElem = document.getElementById(prefix + '_period');
    const period = periodElem ? periodElem.value : '<?= htmlspecialchars($period ?? 'all') ?>';
    const singleDateElem = document.getElementById(prefix + '_single_date');
    const singleDate = singleDateElem ? singleDateElem.value : '';
    const fromDateElem = document.getElementById(prefix + '_from_date');
    const fromDate = fromDateElem ? fromDateElem.value : '';
    const toDateElem = document.getElementById(prefix + '_to_date');
    const toDate = toDateElem ? toDateElem.value : '';

    return { period, singleDate, fromDate, toDate };
}

// Concern Filter Dropdown Change
function onConcernFilterChange(val) {
    const dates = getActiveDateParams();
    let url = '<?= \App\Core\Controller::url('/admin/reports') ?>?';
    if (val) {
        url += 'concern_id=' + encodeURIComponent(val) + '&';
    }
    url += 'period=' + encodeURIComponent(dates.period) +
           '&single_date=' + encodeURIComponent(dates.singleDate) +
           '&from_date=' + encodeURIComponent(dates.fromDate) +
           '&to_date=' + encodeURIComponent(dates.toDate);

    window.location.href = url;
}

function clearConcernFilter() {
    const dates = getActiveDateParams();
    let url = '<?= \App\Core\Controller::url('/admin/reports') ?>?' +
           'period=' + encodeURIComponent(dates.period) +
           '&single_date=' + encodeURIComponent(dates.singleDate) +
           '&from_date=' + encodeURIComponent(dates.fromDate) +
           '&to_date=' + encodeURIComponent(dates.toDate);

    window.location.href = url;
}

// Download report for a single concern (1-click)
function downloadSingleConcern(concernId, format) {
    const dates = getActiveDateParams();
    const url = '<?= \App\Core\Controller::url('/admin/reports/export') ?>' +
                '?concern_id=' + encodeURIComponent(concernId) +
                '&format=' + encodeURIComponent(format) +
                '&period=' + encodeURIComponent(dates.period) +
                '&single_date=' + encodeURIComponent(dates.singleDate) +
                '&from_date=' + encodeURIComponent(dates.fromDate) +
                '&to_date=' + encodeURIComponent(dates.toDate);

    if (format === 'pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}

// Checkbox selection management
function getSelectedRowChecks() {
    return Array.from(document.querySelectorAll('.concern-row-check:checked'));
}

function onRowCheckChange() {
    const checked = getSelectedRowChecks();
    const batchBar = document.getElementById('batch_export_bar');
    const countSpan = document.getElementById('selected_concerns_count');
    const namesSpan = document.getElementById('selected_concerns_names');
    const masterCheck = document.getElementById('master_concern_check');
    const allChecks = document.querySelectorAll('.concern-row-check');

    if (checked.length > 0) {
        batchBar.classList.remove('d-none');
        countSpan.textContent = checked.length;
        const codes = checked.map(c => c.getAttribute('data-code')).join(', ');
        namesSpan.textContent = 'Selected: ' + codes;
    } else {
        batchBar.classList.add('d-none');
    }

    if (masterCheck) {
        masterCheck.checked = (allChecks.length > 0 && checked.length === allChecks.length);
        masterCheck.indeterminate = (checked.length > 0 && checked.length < allChecks.length);
    }
}

function toggleMasterCheck(master) {
    const allChecks = document.querySelectorAll('.concern-row-check');
    allChecks.forEach(cb => { cb.checked = master.checked; });
    onRowCheckChange();
}

function selectAllVisibleConcerns() {
    const allChecks = document.querySelectorAll('.concern-row-check');
    allChecks.forEach(cb => { cb.checked = true; });
    onRowCheckChange();
}

function clearAllSelections() {
    const allChecks = document.querySelectorAll('.concern-row-check');
    allChecks.forEach(cb => { cb.checked = false; });
    onRowCheckChange();
}

// Export combined report for the checked concerns
function exportSelectedCombined(format) {
    const checked = getSelectedRowChecks();
    if (checked.length === 0) {
        alert('Please select at least 1 concern from the table.');
        return;
    }

    const ids = checked.map(c => c.value).join(',');
    const dates = getActiveDateParams();
    const url = '<?= \App\Core\Controller::url('/admin/reports/export') ?>' +
                '?concern_id=' + encodeURIComponent(ids) +
                '&format=' + encodeURIComponent(format) +
                '&period=' + encodeURIComponent(dates.period) +
                '&single_date=' + encodeURIComponent(dates.singleDate) +
                '&from_date=' + encodeURIComponent(dates.fromDate) +
                '&to_date=' + encodeURIComponent(dates.toDate);

    if (format === 'pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}

// Download separated report files (1 file per concern)
function downloadSeparatedReports(format) {
    const checked = getSelectedRowChecks();
    if (checked.length === 0) {
        alert('Please select at least 1 concern from the table.');
        return;
    }

    const dates = getActiveDateParams();
    checked.forEach((cb, index) => {
        const concernId = cb.value;
        const url = '<?= \App\Core\Controller::url('/admin/reports/export') ?>' +
                    '?concern_id=' + encodeURIComponent(concernId) +
                    '&format=' + encodeURIComponent(format) +
                    '&period=' + encodeURIComponent(dates.period) +
                    '&single_date=' + encodeURIComponent(dates.singleDate) +
                    '&from_date=' + encodeURIComponent(dates.fromDate) +
                    '&to_date=' + encodeURIComponent(dates.toDate);

        // Stagger iframe downloads so browser doesn't block parallel downloads
        setTimeout(() => {
            const ifr = document.createElement('iframe');
            ifr.style.display = 'none';
            ifr.src = url;
            document.body.appendChild(ifr);
            setTimeout(() => { document.body.removeChild(ifr); }, 15000);
        }, index * 600);
    });
}
</script>
