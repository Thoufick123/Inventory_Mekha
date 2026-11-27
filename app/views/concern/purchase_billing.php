<div class="container-fluid px-0">
    <style>
        .compact-purchase-bill-table > :not(caption) > * > * {
            padding: 0.4rem 0.5rem;
            vertical-align: middle;
        }
        .compact-purchase-bill-table tbody .badge {
            font-size: 0.68rem;
        }
        .purchase-bill-action-btn {
            width: 28px;
            height: 28px;
            padding: 0 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px !important;
        }
    </style>

    <!-- Quick Metrics Overview Cards -->
    <?php
    $totalBills = count($bills);
    $sumTaxable = 0.0;
    $sumTax = 0.0;
    $sumNet = 0.0;
    $unpaidCount = 0;
    $rollsBillCount = 0;
    $otherBillCount = 0;
    $fgBillCount = 0;
    foreach ($bills as $b) {
        $sumTaxable += (float)($b['total_amount'] ?? 0);
        $sumTax     += (float)($b['tax_amount'] ?? 0);
        $sumNet     += (float)($b['net_amount'] ?? 0);
        if (($b['payment_status'] ?? '') === 'unpaid') $unpaidCount++;
        $bTypes = array_filter(explode(',', strtolower((string)($b['bill_types'] ?? ''))));
        if (in_array('rolls', $bTypes, true)) $rollsBillCount++;
        if (in_array('garment_other', $bTypes, true)) $otherBillCount++;
        if (in_array('finished_goods', $bTypes, true)) $fgBillCount++;
    }
    ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.05em;">Total Invoices</div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $totalBills ?> <span class="fs-6 fw-normal text-muted">Bills</span></h4>
                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-clock-history text-primary me-1"></i><strong><?= $unpaidCount ?></strong> unpaid invoice(s)
                        </div>
                    </div>
                    <div class="rounded-3 p-3 bg-primary-subtle text-primary">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.05em;">Taxable Value</div>
                        <h4 class="fw-bold mb-0 text-dark">₹<?= number_format($sumTaxable, 2) ?></h4>
                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-wallet2 text-info me-1"></i>Pre-tax purchase subtotal
                        </div>
                    </div>
                    <div class="rounded-3 p-3 bg-info-subtle text-info">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.05em;">Total GST Tax</div>
                        <h4 class="fw-bold mb-0 text-dark">₹<?= number_format($sumTaxable > 0 ? $sumTax : 0, 2) ?></h4>
                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-percent text-warning me-1"></i>CGST / SGST / IGST total
                        </div>
                    </div>
                    <div class="rounded-3 p-3 bg-warning-subtle text-warning">
                        <i class="bi bi-percent fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-muted fw-bold mb-1" style="font-size: 0.70rem; letter-spacing: 0.05em;">Net Purchases</div>
                        <h4 class="fw-bold mb-0 text-success">₹<?= number_format($sumNet, 2) ?></h4>
                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-check2-circle text-success me-1"></i>Across all purchase bills
                        </div>
                    </div>
                    <div class="rounded-3 p-3 bg-success-subtle text-success">
                        <i class="bi bi-currency-rupee fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Purchase Invoices Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <!-- Section Navigation Pills + Primary CTA -->
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <ul class="nav nav-pills gap-2 mb-0" id="billTypeTabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link py-2 px-3 fw-semibold d-flex align-items-center gap-2 rounded-2 active bill-type-tab" data-bill-type="">
                        <i class="bi bi-receipt-cutoff"></i>
                        <span>All Invoices</span>
                        <span class="badge bg-white text-primary rounded-pill ms-1"><?= $totalBills ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link py-2 px-3 fw-semibold d-flex align-items-center gap-2 rounded-2 bill-type-tab" data-bill-type="rolls">
                        <i class="bi bi-stack"></i>
                        <span>Fabric Rolls</span>
                        <span class="badge bg-light text-secondary border rounded-pill ms-1"><?= $rollsBillCount ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link py-2 px-3 fw-semibold d-flex align-items-center gap-2 rounded-2 bill-type-tab" data-bill-type="garment_other">
                        <i class="bi bi-scissors"></i>
                        <span>Other Garments</span>
                        <span class="badge bg-light text-secondary border rounded-pill ms-1"><?= $otherBillCount ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link py-2 px-3 fw-semibold d-flex align-items-center gap-2 rounded-2 bill-type-tab" data-bill-type="finished_goods">
                        <i class="bi bi-bag-check"></i>
                        <span>Finished Goods</span>
                        <span class="badge bg-light text-secondary border rounded-pill ms-1"><?= $fgBillCount ?></span>
                    </button>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php
                $exportUrl = \App\Core\Controller::url('/m/purchase_billing/export');
                $prefix = 'pbill_exp';
                include __DIR__ . '/../partials/export_card.php';
                ?>
                <?php if (\App\Core\Auth::can('purchase_billing', 'create')): ?>
                <a href="<?= \App\Core\Controller::url('/m/purchase_billing/create') ?>" class="btn btn-primary btn-sm px-3 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2 rounded-2" style="background: #4f46e5; border-color: #4f46e5;">
                    <i class="bi bi-plus-lg"></i> Record Production Purchase
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 border-bottom bg-light">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="billSearchInput" class="form-control border-start-0 ps-0" placeholder="Search bill #, supplier, invoice #...">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select id="billPaymentFilter" class="form-select form-select-sm">
                        <option value="">-- All Payments --</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partial">Partial</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select id="billInwardFilter" class="form-select form-select-sm">
                        <option value="">-- All Inwards --</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="button" id="billFilterResetBtn" class="btn btn-sm btn-outline-secondary px-3 rounded-2 fw-semibold">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small compact-purchase-bill-table" id="tablePurchaseBills">
                <thead class="table-light">
                    <tr class="fw-bold text-uppercase text-secondary" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                        <th class="ps-3" style="width: 36px;">#</th>
                        <th>Supplier</th>
                        <th>Inv #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-center">Items / Qty</th>
                        <th class="text-end">Taxable</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end table-primary text-primary fw-bold">Net Amount</th>
                        <th class="text-center">Payment</th>
                        <th class="text-center">Status</th>
                        <th class="text-center pe-3" style="width: 108px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="billsTableBody">
                    <?php if (empty($bills)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt display-6 text-secondary d-block mb-2"></i>
                                <strong>No purchase bills recorded yet.</strong>
                                <div class="small mt-1">Click <strong>Record Production Purchase</strong> to create your first invoice.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $bIdx = 1; foreach ($bills as $b): ?>
                            <?php
                            $pmtStatus = strtolower($b['payment_status'] ?? 'unpaid');
                            $inwStatus = strtolower($b['inward_status'] ?? 'pending');
                            $rawTypes = strtolower($b['bill_types'] ?? '');
                            $types = array_filter(explode(',', $rawTypes));
                            ?>
                            <tr class="bill-row"
                                data-billno="<?= strtolower(htmlspecialchars($b['bill_no'])) ?>"
                                data-supplier="<?= strtolower(htmlspecialchars($b['supplier_name'])) ?>"
                                data-invno="<?= strtolower(htmlspecialchars($b['supplier_invoice_no'] ?? '')) ?>"
                                data-types="<?= htmlspecialchars($rawTypes) ?>"
                                data-payment="<?= $pmtStatus ?>"
                                data-inward="<?= $inwStatus ?>">
                                <td class="ps-3 text-muted"><?= $bIdx++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($b['supplier_name']) ?></div>
                                    <?php if (!empty($b['concern_name']) && empty($selectedCid)): ?>
                                        <small class="text-muted"><?= htmlspecialchars($b['concern_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($b['supplier_invoice_no'])): ?>
                                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($b['supplier_invoice_no']) ?></span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= date('d M Y', strtotime($b['bill_date'])) ?></td>
                                <td class="text-nowrap">
                                    <?php if (empty($types)): ?>
                                        <span class="text-muted small">-</span>
                                    <?php else: ?>
                                        <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                            <?php if (in_array('rolls', $types)): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                                    <i class="bi bi-layers-fill me-1"></i>Roll
                                                </span>
                                            <?php endif; ?>
                                            <?php if (in_array('garment_other', $types)): ?>
                                                <span class="badge bg-info-subtle text-dark border border-info-subtle font-monospace">
                                                    <i class="bi bi-scissors me-1"></i>Trims
                                                </span>
                                            <?php endif; ?>
                                            <?php if (in_array('finished_goods', $types)): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">
                                                    <i class="bi bi-bag-check-fill me-1"></i>FG
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= (int)($b['items_count'] ?? 1) ?> Items</span>
                                    <div class="text-muted" style="font-size: 0.70rem;">Qty: <?= number_format((float)($b['total_qty'] ?? 0), 2) ?></div>
                                </td>
                                <td class="text-end text-nowrap">₹<?= number_format($b['total_amount'], 2) ?></td>
                                <td class="text-end text-nowrap">₹<?= number_format($b['tax_amount'], 2) ?></td>
                                <td class="text-end text-nowrap table-primary fw-bold text-primary">₹<?= number_format($b['net_amount'], 2) ?></td>
                                <td class="text-center">
                                    <?php if ($pmtStatus === 'paid'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Paid</span>
                                    <?php elseif ($pmtStatus === 'partial'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info">Partial</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning">Unpaid</span>
                                    <?php endif; ?>
                                    <?php if (!empty($b['payment_method'])): ?>
                                        <div class="text-muted text-capitalize" style="font-size: 0.68rem;"><?= htmlspecialchars(str_replace('_', ' ', $b['payment_method'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= htmlspecialchars($b['sync_badge_class'] ?? 'bg-secondary-subtle text-secondary border') ?>">
                                        <?php if (!empty($b['qty_mismatch'])): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?>
                                        <?= htmlspecialchars($b['sync_label'] ?? ($inwStatus === 'completed' ? 'Completed' : 'Pending')) ?>
                                    </span>
                                </td>
                                <!-- CRUD Actions -->
                                <td class="text-center pe-3">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?= \App\Core\Controller::url('/m/purchase_billing/view?id=' . $b['id']) ?>" class="btn btn-outline-primary purchase-bill-action-btn" title="View Full Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (\App\Core\Auth::can('purchase_billing', 'edit')): ?>
                                        <a href="<?= \App\Core\Controller::url('/m/purchase_billing/edit?id=' . $b['id']) ?>" class="btn btn-outline-secondary purchase-bill-action-btn" title="Edit Purchase Bill">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('purchase_billing', 'delete')): ?>
                                        <button type="button" class="btn btn-outline-danger purchase-bill-action-btn"
                                                title="Delete Bill"
                                                onclick="openDeleteBillModal(<?= (int)$b['id'] ?>, '<?= htmlspecialchars(addslashes($b['bill_no'])) ?>', '<?= htmlspecialchars(addslashes($b['supplier_name'])) ?>', <?= (float)$b['net_amount'] ?>)">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
            <div>
                Showing <strong class="text-dark" id="billsFooterCount"><?= $totalBills ?></strong> invoice(s) in <strong class="text-dark" id="billsFooterTab">All Invoices</strong>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span><i class="bi bi-shield-check text-success me-1"></i>Multi-tenant data isolated</span>
                <span><i class="bi bi-arrow-repeat text-primary me-1"></i>Live purchase ledger synced</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Delete Purchase Bill Confirmation -->
<div class="modal fade" id="modalDeleteBill" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/purchase_billing/delete') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="bill_id" id="deleteBillId" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i> Delete Purchase Bill
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-1 text-dark">
                    Are you sure you want to delete purchase invoice <strong id="deleteBillNo" class="text-dark"></strong> for supplier <strong id="deleteBillSupplier"></strong>?
                </p>
                <div class="alert alert-warning py-2 px-3 small mt-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i> Amount: <strong id="deleteBillAmount"></strong>. Deleting this invoice will permanently remove its associated line items (fabric rolls / trims / finished goods).
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4 fw-semibold">
                    <i class="bi bi-trash3 me-1"></i> Confirm Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Filter Purchase Bills Table
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('billSearchInput');
    const typeTabs = document.querySelectorAll('.bill-type-tab');
    const paymentFilter = document.getElementById('billPaymentFilter');
    const inwardFilter = document.getElementById('billInwardFilter');
    const resetBtn = document.getElementById('billFilterResetBtn');
    const rows = document.querySelectorAll('.bill-row');
    const footerCount = document.getElementById('billsFooterCount');
    const footerTab = document.getElementById('billsFooterTab');
    let activeBillType = '';

    function filterBills() {
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const bType = activeBillType;
        const pmt = (paymentFilter ? paymentFilter.value : '').trim().toLowerCase();
        const inw = (inwardFilter ? inwardFilter.value : '').trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(function (row) {
            const billNo = row.dataset.billno || '';
            const supplier = row.dataset.supplier || '';
            const invNo = row.dataset.invno || '';
            const rowTypes = row.dataset.types || '';
            const rowPmt = row.dataset.payment || '';
            const rowInw = row.dataset.inward || '';

            const matchesQuery = !query || billNo.includes(query) || supplier.includes(query) || invNo.includes(query);
            const matchesType = !bType || rowTypes.includes(bType);
            const matchesPayment = !pmt || rowPmt === pmt;
            const matchesInward = !inw || rowInw === inw;

            if (matchesQuery && matchesType && matchesPayment && matchesInward) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (footerCount) {
            footerCount.textContent = visibleCount;
        }
    }

    typeTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            typeTabs.forEach(function (t) {
                t.classList.remove('active');
                const badge = t.querySelector('.badge');
                if (badge) {
                    badge.classList.remove('bg-white', 'text-primary');
                    badge.classList.add('bg-light', 'text-secondary', 'border');
                }
            });
            tab.classList.add('active');
            const activeBadge = tab.querySelector('.badge');
            if (activeBadge) {
                activeBadge.classList.remove('bg-light', 'text-secondary', 'border');
                activeBadge.classList.add('bg-white', 'text-primary');
            }
            activeBillType = (tab.dataset.billType || '').trim().toLowerCase();
            if (footerTab) {
                const labelEl = tab.querySelector('span');
                footerTab.textContent = labelEl ? labelEl.textContent : 'All Invoices';
            }
            filterBills();
        });
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (paymentFilter) paymentFilter.value = '';
            if (inwardFilter) inwardFilter.value = '';
            const allTab = document.querySelector('.bill-type-tab[data-bill-type=""]');
            if (allTab) {
                allTab.click();
            } else {
                activeBillType = '';
                filterBills();
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterBills);
    if (paymentFilter) paymentFilter.addEventListener('change', filterBills);
    if (inwardFilter) inwardFilter.addEventListener('change', filterBills);
});

// Delete Bill Modal Handler
function openDeleteBillModal(id, billNo, supplier, netAmount) {
    document.getElementById('deleteBillId').value = id;
    document.getElementById('deleteBillNo').textContent = '#' + billNo;
    document.getElementById('deleteBillSupplier').textContent = supplier;
    document.getElementById('deleteBillAmount').textContent = '₹' + parseFloat(netAmount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const modal = new bootstrap.Modal(document.getElementById('modalDeleteBill'));
    modal.show();
}
</script>
