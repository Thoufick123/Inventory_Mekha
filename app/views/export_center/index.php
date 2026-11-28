<?php
$isSuperAdmin = $ctx['is_super_admin'];
$concerns = $ctx['concerns'];
$selectedCids = $ctx['concern_ids'];
$activeConcern = $ctx['active_concern'];
$warehouses = $ctx['warehouses'];
$enabledCodes = $ctx['enabled_codes'];

$totalModules = 0;
foreach ($categories as $items) {
    $totalModules += count($items);
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
$currentYear = (int)date('Y');
$years = range($currentYear - 3, $currentYear + 1);
?>
<style>
.ec-top-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:18px; }
@media (max-width: 991px) { .ec-top-cards { grid-template-columns:1fr; } }
.ec-info-card {
    background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px;
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    box-shadow:0 1px 2px rgba(15,23,42,.04);
}
.ec-info-label { font-size:.7rem; font-weight:700; letter-spacing:.04em; color:#64748b; text-transform:uppercase; margin-bottom:4px; }
.ec-info-title { font-size:1rem; font-weight:700; color:#881337; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.ec-info-title.teal { color:#0f766e; }
.ec-info-title.blue { color:#1d4ed8; }
.ec-info-sub { font-size:.75rem; color:#64748b; margin-top:3px; }
.ec-info-icon {
    width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:1.05rem; flex-shrink:0;
}
.ec-icon-rose { background:#fdf2f8; color:#9d174d; }
.ec-icon-teal { background:#ecfdf5; color:#047857; }
.ec-icon-blue { background:#eff6ff; color:#0284c7; }

.ec-main-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 1px 3px rgba(15,23,42,.04); overflow:hidden; }
.ec-main-header {
    padding:14px 20px; border-bottom:1px solid #f1f5f9;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
}
.ec-main-title { font-size:1rem; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px; margin:0; }
.ec-badge-maroon { background:#fdf2f8; color:#9d174d; border:1px solid #fbcfe8; font-size:.74rem; font-weight:600; padding:4px 10px; border-radius:999px; }

.ec-section { padding:18px 20px; border-bottom:1px solid #f1f5f9; }
.ec-step-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px; }
.ec-step-title { font-size:.9rem; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px; }
.ec-step-num {
    width:20px; height:20px; border-radius:50%; background:#881337; color:#fff;
    font-size:.72rem; font-weight:700; display:inline-flex; align-items:center; justify-content:center;
}
.ec-seg-group { display:inline-flex; border:1px solid #cbd5e1; border-radius:8px; overflow:hidden; background:#fff; }
.ec-seg-btn {
    border:0; background:transparent; padding:6px 14px; font-size:.78rem; font-weight:600; color:#475569; cursor:pointer;
}
.ec-seg-btn.active { background:#881337; color:#fff; }

.ec-box {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px;
}
.ec-concern-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-top:10px; }
@media (max-width: 991px) { .ec-concern-grid { grid-template-columns:1fr; } }
.ec-concern-card {
    background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px;
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px; cursor:pointer;
    transition:border-color .15s, box-shadow .15s;
}
.ec-concern-card.selected { border-color:#881337; box-shadow:0 0 0 2px rgba(136,19,55,.1); }
.ec-concern-code {
    background:#fdf2f8; color:#9d174d; border:1px solid #fbcfe8; font-size:.68rem; font-weight:700;
    padding:2px 7px; border-radius:6px; white-space:nowrap;
}

.ec-cat-title {
    font-size:.73rem; font-weight:700; letter-spacing:.05em; color:#64748b; text-transform:uppercase;
    margin:18px 0 10px; display:flex; align-items:center; gap:6px;
}
.ec-mod-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
@media (max-width: 1199px) { .ec-mod-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width: 767px) { .ec-mod-grid { grid-template-columns:1fr; } }

.ec-mod-card {
    background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;
    cursor:pointer; transition:all .15s ease; display:flex; flex-direction:column; gap:5px;
}
.ec-mod-card:hover { border-color:#cbd5e1; background:#fcfcfd; }
.ec-mod-card.selected { border-color:#881337; background:#fffafb; }
.ec-mod-card.dimmed { opacity:.45; }
.ec-mod-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.ec-mod-left { display:flex; align-items:center; gap:8px; font-size:.86rem; font-weight:700; color:#0f172a; }
.ec-mod-desc { font-size:.74rem; color:#64748b; line-height:1.35; padding-left:24px; }
.ec-rec-pill {
    font-size:.7rem; font-weight:700; padding:2px 8px; border-radius:6px; white-space:nowrap;
    background:#f1f5f9; color:#64748b;
}
.ec-rec-pill.has-data { background:#dcfce7; color:#15803d; }

.ec-footer-bar {
    padding:16px 20px; background:#f8fafc; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
}
.ec-btn-maroon {
    background:#881337; color:#fff; border:0; border-radius:8px; padding:9px 18px; font-size:.85rem; font-weight:600;
}
.ec-btn-maroon:hover { background:#70102d; color:#fff; }
</style>

<div class="container-fluid py-3">
    <!-- Top 3 Summary Cards -->
    <div class="ec-top-cards">
        <div class="ec-info-card">
            <div>
                <div class="ec-info-label">Active Business Concern</div>
                <div class="ec-info-title">
                    <?php if ($isSuperAdmin && empty($activeConcern)): ?>
                        Global Admin <span class="badge bg-dark" style="font-size:.65rem;">ADMIN</span>
                    <?php elseif (!empty($activeConcern)): ?>
                        <?= htmlspecialchars($activeConcern['name']) ?>
                        <span class="ec-concern-code"><?= htmlspecialchars($activeConcern['code'] ?? 'CONCERN') ?></span>
                    <?php else: ?>
                        All Concerns
                    <?php endif; ?>
                </div>
                <div class="ec-info-sub">
                    <?= ($isSuperAdmin && empty($activeConcern))
                        ? 'System Administrator (All Concerns)'
                        : htmlspecialchars($activeConcern['gstin'] ?: ($activeConcern['address'] ?: 'Scoped Business Concern')) ?>
                </div>
            </div>
            <div class="ec-info-icon ec-icon-rose"><i class="bi bi-graph-up-arrow"></i></div>
        </div>

        <div class="ec-info-card">
            <div>
                <div class="ec-info-label">Export Format Support</div>
                <div class="ec-info-title teal">Excel CSV &amp; Native Workbook</div>
                <div class="ec-info-sub">Native decimal formatting with UTF-8 BOM</div>
            </div>
            <div class="ec-info-icon ec-icon-teal"><i class="bi bi-file-earmark-spreadsheet"></i></div>
        </div>

        <div class="ec-info-card">
            <div>
                <div class="ec-info-label">Query Engine Optimization</div>
                <div class="ec-info-title blue">High-Speed SARGable Range Scan</div>
                <div class="ec-info-sub">Indexed date predicates &amp; pre-joined aggregations</div>
            </div>
            <div class="ec-info-icon ec-icon-blue"><i class="bi bi-lightning-charge"></i></div>
        </div>
    </div>

    <!-- Main Configure Export Dataset Form -->
    <form method="POST" action="<?= \App\Core\Controller::url($basePath . '/download') ?>" id="exportCenterForm">
        <input type="hidden" name="format" id="exportFormatInput" value="csv">
        <input type="hidden" name="period_mode" id="periodModeInput" value="<?= htmlspecialchars($dateRange['mode']) ?>">

        <div class="ec-main-card">
            <div class="ec-main-header">
                <h5 class="ec-main-title">
                    <i class="bi bi-sliders2 text-danger"></i>
                    Configure Export Dataset
                </h5>
                <span class="ec-badge-maroon"><?= $totalModules ?> Data Modules Available</span>
            </div>

            <!-- Step 1: Select Export Date Period -->
            <div class="ec-section">
                <div class="ec-step-header">
                    <div class="ec-step-title">
                        <span class="ec-step-num">1</span>
                        Select Export Date Period
                    </div>
                    <div class="ec-seg-group">
                        <button type="button" class="ec-seg-btn <?= $dateRange['mode'] === 'month' ? 'active' : '' ?>" data-mode="month">Month-Wise</button>
                        <button type="button" class="ec-seg-btn <?= $dateRange['mode'] === 'custom' ? 'active' : '' ?>" data-mode="custom">Custom Date Range</button>
                    </div>
                </div>

                <div id="monthWiseInputs" class="row g-3 <?= $dateRange['mode'] === 'month' ? '' : 'd-none' ?>">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Select Month</label>
                        <select name="month" id="ecMonth" class="form-select ec-filter-trigger">
                            <?php foreach ($months as $num => $label): ?>
                                <option value="<?= $num ?>" <?= $dateRange['month'] === $num ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Select Year</label>
                        <select name="year" id="ecYear" class="form-select ec-filter-trigger">
                            <?php foreach ($years as $yr): ?>
                                <option value="<?= $yr ?>" <?= $dateRange['year'] === $yr ? 'selected' : '' ?>><?= $yr ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="customRangeInputs" class="row g-3 <?= $dateRange['mode'] === 'custom' ? '' : 'd-none' ?>">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">From Date</label>
                        <input type="date" name="from_date" id="ecFromDate" class="form-control ec-filter-trigger" value="<?= htmlspecialchars($dateRange['start_date']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">To Date</label>
                        <input type="date" name="to_date" id="ecToDate" class="form-control ec-filter-trigger" value="<?= htmlspecialchars($dateRange['end_date']) ?>">
                    </div>
                </div>

                <!-- Filter by Business Concern -->
                <div class="ec-box mt-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <div class="fw-bold small text-dark"><i class="bi bi-building me-1 text-danger"></i> Filter by Business Concern</div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <?= $isSuperAdmin ? 'Choose one or more entities, or leave all unselected for a consolidated export.' : 'Scoped to your assigned business concern.' ?>
                            </div>
                        </div>
                        <?php if ($isSuperAdmin): ?>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAllConcerns"><i class="bi bi-check2-all me-1"></i>All Concerns</button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted" id="btnClearConcerns">Clear</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="ec-concern-grid">
                        <?php foreach ($concerns as $c):
                            $cid = (int)$c['id'];
                            $checked = !$isSuperAdmin || in_array($cid, $selectedCids, true);
                        ?>
                        <label class="ec-concern-card <?= $checked ? 'selected' : '' ?>">
                            <div class="d-flex align-items-start gap-2">
                                <input type="checkbox"
                                       class="form-check-input mt-1 ec-concern-cb ec-filter-trigger"
                                       name="concern_ids[]"
                                       value="<?= $cid ?>"
                                       <?= $checked ? 'checked' : '' ?>
                                       <?= !$isSuperAdmin ? 'onclick="return false;"' : '' ?>>
                                <div>
                                    <div class="fw-bold small text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($c['address'] ?: 'Business Concern') ?></div>
                                    <?php if (!empty($c['gstin'])): ?>
                                        <div class="text-muted" style="font-size:.7rem;">GSTIN: <?= htmlspecialchars($c['gstin']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="ec-concern-code"><?= htmlspecialchars($c['code'] ?? ('C-' . $cid)) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
                        <span class="text-muted" style="font-size:.73rem;">The selected concerns scope procurement, manufacturing, warehousing, and financial records.</span>
                        <span class="ec-badge-maroon" id="concernScopeBadge">
                            <?= empty($selectedCids) && $isSuperAdmin ? 'All Business Concerns (Consolidated)' : count($selectedCids ?: $concerns) . ' Concern(s) Selected' ?>
                        </span>
                    </div>
                </div>

                <!-- Filter by Consignee / Destination Warehouse -->
                <div class="ec-box mt-3">
                    <label class="form-label fw-bold small text-dark mb-2">
                        <i class="bi bi-buildings me-1 text-secondary"></i> Filter by Consignee / Destination Warehouse
                    </label>
                    <select name="unit_ids[]" id="ecUnitIds" class="form-select ec-filter-trigger" multiple size="3">
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= (int)$wh['id'] ?>" <?= in_array((int)$wh['id'], $selectedUnits, true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wh['name']) ?> [<?= htmlspecialchars($wh['code']) ?>] [<?= htmlspecialchars($wh['concern_code']) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-muted mt-1" style="font-size:.72rem;">Select one or more warehouses to filter outward dispatch and inward receipt data. Leave unselected to include every warehouse.</div>
                </div>
            </div>

            <!-- Step 2: Choose Data Modules to Include -->
            <div class="ec-section">
                <div class="ec-step-header">
                    <div class="ec-step-title">
                        <span class="ec-step-num">2</span>
                        Choose Data Modules to Include
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="toggleOnlyWithData">
                            <label class="form-check-label small fw-semibold text-muted" for="toggleOnlyWithData">Only With Data</label>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-danger" id="btnSelectConcernModules"><i class="bi bi-check2-square me-1"></i>Select Concern Modules</button>
                            <button type="button" class="btn btn-outline-secondary" id="btnSelectAllModules">Select All</button>
                            <button type="button" class="btn btn-outline-secondary" id="btnClearModules">Clear</button>
                        </div>
                    </div>
                </div>

                <?php foreach ($categories as $catName => $modules): ?>
                    <div class="ec-cat-title">
                        <i class="bi bi-caret-right-fill text-danger"></i>
                        <?= htmlspecialchars($catName) ?>
                    </div>
                    <div class="ec-mod-grid">
                        <?php foreach ($modules as $mod):
                            $key = $mod['key'];
                            $cnt = (int)($counts[$key] ?? 0);
                            $isEnabledForConcern = empty($enabledCodes) || in_array($mod['module_code'], $enabledCodes, true);
                        ?>
                        <label class="ec-mod-card selected"
                               data-key="<?= htmlspecialchars($key) ?>"
                               data-module-code="<?= htmlspecialchars($mod['module_code']) ?>"
                               data-count="<?= $cnt ?>">
                            <div class="ec-mod-top">
                                <div class="ec-mod-left">
                                    <input type="checkbox"
                                           class="form-check-input ec-mod-cb"
                                           name="modules[]"
                                           value="<?= htmlspecialchars($key) ?>"
                                           checked>
                                    <i class="bi <?= htmlspecialchars($mod['icon']) ?> text-danger"></i>
                                    <span><?= htmlspecialchars($mod['title']) ?></span>
                                </div>
                                <span class="ec-rec-pill <?= $cnt > 0 ? 'has-data' : '' ?>" id="pill-<?= htmlspecialchars($key) ?>">
                                    <?= number_format($cnt) ?> <?= $cnt === 1 ? 'record' : 'records' ?>
                                </span>
                            </div>
                            <div class="ec-mod-desc"><?= htmlspecialchars($mod['description']) ?></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer Action Bar -->
            <div class="ec-footer-bar">
                <div class="small text-muted">
                    <strong id="selectedModCount"><?= $totalModules ?></strong> modules selected &bull;
                    Period: <strong id="activePeriodLabel"><?= htmlspecialchars($dateRange['label']) ?></strong>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="submit" class="btn btn-outline-success btn-sm fw-semibold px-3 py-2" onclick="document.getElementById('exportFormatInput').value='xls';">
                        <i class="bi bi-file-earmark-excel me-1"></i> Download Excel (.xls)
                    </button>
                    <button type="submit" class="ec-btn-maroon" onclick="document.getElementById('exportFormatInput').value='csv';">
                        <i class="bi bi-download me-1"></i> Export Consolidated CSV
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const basePath = <?= json_encode(\App\Core\Controller::url($basePath)) ?>;
    let enabledCodes = <?= json_encode(array_values($enabledCodes)) ?>;
    const isSuperAdmin = <?= json_encode($isSuperAdmin) ?>;

    const periodModeInput = document.getElementById('periodModeInput');
    const monthWiseInputs = document.getElementById('monthWiseInputs');
    const customRangeInputs = document.getElementById('customRangeInputs');
    const onlyWithDataToggle = document.getElementById('toggleOnlyWithData');

    // Period mode switch
    document.querySelectorAll('.ec-seg-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const mode = btn.dataset.mode;
            periodModeInput.value = mode;
            document.querySelectorAll('.ec-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
            monthWiseInputs.classList.toggle('d-none', mode !== 'month');
            customRangeInputs.classList.toggle('d-none', mode !== 'custom');
            refreshCounts();
        });
    });

    // Concern card highlight + All/Clear buttons
    function syncConcernUI() {
        const cbs = Array.from(document.querySelectorAll('.ec-concern-cb'));
        let selectedCount = 0;
        cbs.forEach(cb => {
            cb.closest('.ec-concern-card').classList.toggle('selected', cb.checked);
            if (cb.checked) selectedCount++;
        });
        const badge = document.getElementById('concernScopeBadge');
        if (badge) {
            badge.textContent = (isSuperAdmin && selectedCount === 0)
                ? 'All Business Concerns (Consolidated)'
                : selectedCount + ' Concern(s) Selected';
        }
    }

    document.getElementById('btnAllConcerns')?.addEventListener('click', () => {
        document.querySelectorAll('.ec-concern-cb').forEach(cb => cb.checked = true);
        syncConcernUI();
        refreshCounts();
    });
    document.getElementById('btnClearConcerns')?.addEventListener('click', () => {
        document.querySelectorAll('.ec-concern-cb').forEach(cb => cb.checked = false);
        syncConcernUI();
        refreshCounts();
    });

    // Module selection controls
    function syncModuleUI() {
        let count = 0;
        const onlyData = onlyWithDataToggle.checked;
        document.querySelectorAll('.ec-mod-card').forEach(card => {
            const cb = card.querySelector('.ec-mod-cb');
            const recCount = parseInt(card.dataset.count || '0', 10);
            if (onlyData && recCount === 0) {
                cb.checked = false;
                card.classList.add('dimmed');
            } else {
                card.classList.remove('dimmed');
            }
            card.classList.toggle('selected', cb.checked);
            if (cb.checked) count++;
        });
        document.getElementById('selectedModCount').textContent = count;
    }

    document.querySelectorAll('.ec-mod-cb').forEach(cb => {
        cb.addEventListener('change', syncModuleUI);
    });

    onlyWithDataToggle.addEventListener('change', () => {
        if (onlyWithDataToggle.checked) {
            document.querySelectorAll('.ec-mod-card').forEach(card => {
                const recCount = parseInt(card.dataset.count || '0', 10);
                card.querySelector('.ec-mod-cb').checked = recCount > 0;
            });
        }
        syncModuleUI();
    });

    document.getElementById('btnSelectAllModules').addEventListener('click', () => {
        onlyWithDataToggle.checked = false;
        document.querySelectorAll('.ec-mod-cb').forEach(cb => cb.checked = true);
        syncModuleUI();
    });

    document.getElementById('btnClearModules').addEventListener('click', () => {
        onlyWithDataToggle.checked = false;
        document.querySelectorAll('.ec-mod-cb').forEach(cb => cb.checked = false);
        syncModuleUI();
    });

    document.getElementById('btnSelectConcernModules').addEventListener('click', () => {
        onlyWithDataToggle.checked = false;
        document.querySelectorAll('.ec-mod-card').forEach(card => {
            const code = card.dataset.moduleCode;
            const cb = card.querySelector('.ec-mod-cb');
            cb.checked = enabledCodes.length === 0 || enabledCodes.includes(code);
        });
        syncModuleUI();
    });

    // Live AJAX count refresh
    function refreshCounts() {
        syncConcernUI();
        const params = new URLSearchParams();
        params.set('period_mode', periodModeInput.value);
        params.set('month', document.getElementById('ecMonth').value);
        params.set('year', document.getElementById('ecYear').value);
        params.set('from_date', document.getElementById('ecFromDate').value);
        params.set('to_date', document.getElementById('ecToDate').value);

        document.querySelectorAll('.ec-concern-cb:checked').forEach(cb => {
            params.append('concern_id[]', cb.value);
        });
        Array.from(document.getElementById('ecUnitIds').selectedOptions).forEach(opt => {
            params.append('unit_ids[]', opt.value);
        });

        fetch(basePath + '/counts?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (data.enabled_codes) enabledCodes = data.enabled_codes;
                if (data.date_range && data.date_range.label) {
                    document.getElementById('activePeriodLabel').textContent = data.date_range.label;
                }
                if (data.counts) {
                    Object.entries(data.counts).forEach(([key, val]) => {
                        const card = document.querySelector(`.ec-mod-card[data-key="${key}"]`);
                        const pill = document.getElementById('pill-' + key);
                        if (card) card.dataset.count = val;
                        if (pill) {
                            pill.textContent = val.toLocaleString() + (val === 1 ? ' record' : ' records');
                            pill.classList.toggle('has-data', val > 0);
                        }
                    });
                }
                syncModuleUI();
            })
            .catch(() => {});
    }

    document.querySelectorAll('.ec-filter-trigger').forEach(el => {
        el.addEventListener('change', refreshCounts);
    });

    syncConcernUI();
    syncModuleUI();
});
</script>
