<?php
/**
 * Reusable Enterprise Export Toolbar Card Component
 * Strictly conforms to Invento ERP Standard Export Flow (Excel, PDF, CSV & 1-Click Fast Export)
 * 
 * Expected variables in calling scope:
 * @var string $exportTitle     Card heading (e.g. "Export Stocks Inventory")
 * @var string $exportSubtitle  Card subtext description
 * @var string $exportUrl       Backend endpoint URL (e.g. \App\Core\Controller::url('/m/stocks/export'))
 * @var string|null $prefix     Unique ID prefix for form fields (default: 'exp_' . substr(md5($exportUrl), 0, 6))
 * @var string|null $defaultPeriod Default period (default: 'all')
 */

$prefix = $prefix ?? ('exp_' . substr(md5($exportUrl ?? 'def'), 0, 8));
$defaultPeriod = $defaultPeriod ?? 'all';

// Determine the target module for export authorization
$targetExportModule = $currentModule ?? null;
if (!$targetExportModule && !empty($exportUrl)) {
    if (preg_match('#/(?:m/)?([a-z0-9_]+)/export#i', $exportUrl, $matches)) {
        $targetExportModule = $matches[1];
        if ($targetExportModule === 'stock_ledger') $targetExportModule = 'stocks';
        if ($targetExportModule === 'cash') $targetExportModule = 'cash_flow';
    }
}

// Restrict Export toolbar: View-only users cannot export data. Super admins and users with add/edit can export.
if (!\App\Core\Auth::isSuperAdmin()) {
    $canExport = $targetExportModule ? \App\Core\Auth::can($targetExportModule, 'export') : false;
    if (!$canExport) {
        return; // Suppress export card entirely for view-only users
    }
}
?>

<div class="card border-0 shadow-sm rounded-4 <?= !empty($exportTitle) ? 'p-3' : 'p-2' ?> bg-white mb-4" style="border: 1px solid #e2e8f0 !important;">
    <div class="d-flex flex-wrap <?= !empty($exportTitle) ? 'justify-content-between' : 'justify-content-end' ?> align-items-center gap-3">
        <?php if (!empty($exportTitle)): ?>
        <!-- Card Icon & Title Header -->
        <div class="d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; background: #ecfdf5; color: #16a34a; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; border: 1px solid #bbf7d0;">
                <i class="bi bi-file-earmark-excel"></i>
            </div>
            <div>
                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($exportTitle) ?></h6>
                <?php if (!empty($exportSubtitle)): ?>
                <small class="text-muted"><?= htmlspecialchars($exportSubtitle) ?></small>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Period Select & Export Action Buttons -->
        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Period Selector -->
            <div class="d-flex align-items-center gap-1">
                <label class="small text-muted mb-0 fw-semibold" style="font-size: 0.76rem;">Period:</label>
                <select id="<?= $prefix ?>_period" class="form-select form-select-sm" style="width: 175px; border-radius: 8px; font-size: 0.85rem;" onchange="toggleExportCardDates('<?= $prefix ?>')">
                    <option value="all" <?= ($defaultPeriod === 'all') ? 'selected' : '' ?>>All Time</option>
                    <option value="single_day" <?= ($defaultPeriod === 'single_day') ? 'selected' : '' ?>>Single Day</option>
                    <option value="this_month" <?= ($defaultPeriod === 'this_month') ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= ($defaultPeriod === 'last_month') ? 'selected' : '' ?>>Last Month</option>
                    <option value="3_months" <?= ($defaultPeriod === '3_months') ? 'selected' : '' ?>>Particular 3 Months</option>
                    <option value="6_months" <?= ($defaultPeriod === '6_months') ? 'selected' : '' ?>>Six Months</option>
                    <option value="custom" <?= ($defaultPeriod === 'custom') ? 'selected' : '' ?>>Custom Date Range</option>
                </select>
            </div>

            <!-- Single Date Picker (Shown for single_day) -->
            <div id="<?= $prefix ?>_single_date_box" class="d-none align-items-center gap-1">
                <input type="date" id="<?= $prefix ?>_single_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 130px; border-radius: 8px; font-size: 0.82rem;">
            </div>

            <!-- Custom Range Pickers (Shown for custom) -->
            <div id="<?= $prefix ?>_custom_date_box" class="d-none align-items-center gap-1">
                <input type="date" id="<?= $prefix ?>_from_date" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>" style="width: 125px; border-radius: 8px; font-size: 0.82rem;">
                <span class="text-muted small">to</span>
                <input type="date" id="<?= $prefix ?>_to_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 125px; border-radius: 8px; font-size: 0.82rem;">
            </div>

            <!-- Export Dropdown Button -->
            <div class="dropdown">
                <button type="button" class="btn btn-success btn-sm px-3 fw-bold d-flex align-items-center gap-2 shadow-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px; height: 35px; background: #16a34a; border: none;">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm py-1" style="border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.88rem; min-width: 170px;">
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-success" href="javascript:void(0)" onclick="triggerCardExport('<?= $prefix ?>', 'excel', '<?= $exportUrl ?>')">
                            <i class="bi bi-file-earmark-excel fs-6"></i> Export Excel
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-danger" href="javascript:void(0)" onclick="triggerCardExport('<?= $prefix ?>', 'pdf', '<?= $exportUrl ?>')">
                            <i class="bi bi-file-earmark-pdf fs-6"></i> Export PDF
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-secondary" href="javascript:void(0)" onclick="triggerCardExport('<?= $prefix ?>', 'csv', '<?= $exportUrl ?>')">
                            <i class="bi bi-file-earmark-arrow-down fs-6"></i> CSV
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
if (typeof window.triggerCardExport === 'undefined') {
    window.triggerCardExport = function(prefix, format, endpoint) {
        const periodElem = document.getElementById(prefix + '_period');
        const period = periodElem ? periodElem.value : 'all';
        const singleDateElem = document.getElementById(prefix + '_single_date');
        const singleDate = singleDateElem ? singleDateElem.value : '';
        const fromDateElem = document.getElementById(prefix + '_from_date');
        const fromDate = fromDateElem ? fromDateElem.value : '';
        const toDateElem = document.getElementById(prefix + '_to_date');
        const toDate = toDateElem ? toDateElem.value : '';

        let sep = endpoint.indexOf('?') !== -1 ? '&' : '?';
        let url = endpoint + sep +
                  'format=' + encodeURIComponent(format) +
                  '&period=' + encodeURIComponent(period) +
                  '&single_date=' + encodeURIComponent(singleDate) +
                  '&from_date=' + encodeURIComponent(fromDate) +
                  '&to_date=' + encodeURIComponent(toDate);

        if (format === 'pdf') {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    };

    window.quickCardExport = function(prefix, format, period, endpoint) {
        const today = new Date().toISOString().split('T')[0];
        let sep = endpoint.indexOf('?') !== -1 ? '&' : '?';
        let url = endpoint + sep +
                  'format=' + encodeURIComponent(format) +
                  '&period=' + encodeURIComponent(period) +
                  '&single_date=' + encodeURIComponent(today);

        if (format === 'pdf') {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    };

    window.toggleExportCardDates = function(prefix) {
        const sel = document.getElementById(prefix + '_period');
        if (!sel) return;
        const v = sel.value;
        const singleBox = document.getElementById(prefix + '_single_date_box');
        const customBox = document.getElementById(prefix + '_custom_date_box');

        if (singleBox) {
            if (v === 'single_day') {
                singleBox.classList.remove('d-none');
                singleBox.classList.add('d-flex');
            } else {
                singleBox.classList.remove('d-flex');
                singleBox.classList.add('d-none');
            }
        }

        if (customBox) {
            if (v === 'custom') {
                customBox.classList.remove('d-none');
                customBox.classList.add('d-flex');
            } else {
                customBox.classList.remove('d-flex');
                customBox.classList.add('d-none');
            }
        }
    };
}

// Automatically initialize date boxes for this prefix on render
(function() {
    toggleExportCardDates('<?= $prefix ?>');
})();
</script>
