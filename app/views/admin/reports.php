<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <div class="text-secondary small mb-1">
                <a href="<?= \App\Core\Controller::url('/admin/dashboard') ?>" class="text-decoration-none text-muted">Super Admin</a>
                <span class="mx-1">/</span>
                <span class="text-dark fw-semibold">Consolidated Reports</span>
            </div>
            <h4 class="fw-bold mb-1 text-dark">Consolidated Multi-Concern Performance Report</h4>
            <p class="text-secondary small mb-0">Cross-tenant inventory valuation, revenue volume, and procurement metrics aggregated across all concerns.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                <i class="bi bi-calendar-check me-1"></i> <?= htmlspecialchars($rangeLabel ?? 'All Time') ?>
            </span>
        </div>
    </div>

    <!-- Export Intelligence Toolbar Card -->
    <?php
    $exportTitle = 'Export Enterprise Reports (Excel & PDF)';
    $exportSubtitle = 'Generate consolidated tenant performance, inventory valuation, purchases & revenue as per date period';
    $exportUrl = \App\Core\Controller::url('/admin/reports/export');
    $prefix = 'admin_rep_exp';
    $defaultPeriod = $period ?? 'all';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Summary Metrics Table -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border: 1px solid #e2e8f0 !important;">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-line me-2 text-primary"></i> Tenant Performance Breakdown</h6>
            <span class="badge bg-light text-dark border"><?= count($concernStats) ?> Active Concerns</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th>Concern</th>
                        <th>Variants Mastered</th>
                        <th>Stock Units In Hand</th>
                        <th>Live Inventory Valuation</th>
                        <th>Gross Purchases in Period</th>
                        <th>Gross Invoiced Sales in Period</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandStockQty = 0;
                    $grandValuation = 0;
                    $grandPurchases = 0;
                    $grandSales = 0;

                    foreach ($concernStats as $cs): 
                        $grandStockQty += (float)$cs['total_stock_qty'];
                        $grandValuation += (float)$cs['stock_valuation'];
                        $grandPurchases += (float)$cs['total_purchases'];
                        $grandSales += (float)$cs['total_sales'];
                    ?>
                        <tr>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOTAL CONSOLIDATED</td>
                        <td>-</td>
                        <td class="font-monospace"><?= number_format($grandStockQty, 2) ?></td>
                        <td class="text-primary font-monospace">₹<?= number_format($grandValuation, 2) ?></td>
                        <td class="font-monospace">₹<?= number_format($grandPurchases, 2) ?></td>
                        <td class="text-success font-monospace">₹<?= number_format($grandSales, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelect = document.getElementById('admin_report_period');
    const singleDateBox = document.getElementById('admin_single_date_box');
    const customDateBox = document.getElementById('admin_custom_date_box');

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

function triggerAdminExport(format) {
    const period = document.getElementById('admin_report_period').value;
    const singleDate = document.getElementById('admin_single_date') ? document.getElementById('admin_single_date').value : '';
    const fromDate = document.getElementById('admin_from_date') ? document.getElementById('admin_from_date').value : '';
    const toDate = document.getElementById('admin_to_date') ? document.getElementById('admin_to_date').value : '';

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
