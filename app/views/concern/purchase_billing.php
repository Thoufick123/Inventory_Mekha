<div class="container-fluid px-0">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Purchase Billing</h4>
            <p class="text-secondary small mb-0">Record vendor purchase bills, fabric rolls, trims/accessories and finished goods with GST calculation.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (\App\Core\Auth::can('purchase_billing', 'create')): ?>
            <a href="<?= \App\Core\Controller::url('/m/purchase_billing/create') ?>" class="btn text-white shadow-sm px-3 py-2 fw-semibold rounded-2 d-flex align-items-center gap-2" style="background: #4f46e5;">
                <i class="bi bi-bag-plus fs-6"></i> Record Production Purchase
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Purchase Invoices (Excel & PDF)';
    $exportSubtitle = 'Download vendor bills, procurement totals, GST tax breakdown & payment settlement status';
    $exportUrl = \App\Core\Controller::url('/m/purchase_billing/export');
    $prefix = 'pbill_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Quick Metrics Overview Cards -->
    <?php
    $totalBills = count($bills);
    $sumTaxable = 0.0;
    $sumTax = 0.0;
    $sumNet = 0.0;
    $unpaidCount = 0;
    foreach ($bills as $b) {
        $sumTaxable += (float)($b['total_amount'] ?? 0);
        $sumTax     += (float)($b['tax_amount'] ?? 0);
        $sumNet     += (float)($b['net_amount'] ?? 0);
        if (($b['payment_status'] ?? '') === 'unpaid') $unpaidCount++;
    }
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total Invoices</span>
                        <h4 class="fw-bold mb-0 text-dark mt-1"><?= $totalBills ?></h4>
                    </div>
                    <div class="rounded-circle p-3 bg-primary-subtle text-primary">
                        <i class="bi bi-receipt fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Taxable Value</span>
                        <h4 class="fw-bold mb-0 text-dark mt-1">₹<?= number_format($sumTaxable, 2) ?></h4>
                    </div>
                    <div class="rounded-circle p-3 bg-info-subtle text-info">
                        <i class="bi bi-wallet2 fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Total GST Tax</span>
                        <h4 class="fw-bold mb-0 text-dark mt-1">₹<?= number_format($sumTaxable > 0 ? $sumTax : 0, 2) ?></h4>
                    </div>
                    <div class="rounded-circle p-3 bg-warning-subtle text-warning">
                        <i class="bi bi-percent fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Net Purchases</span>
                        <h4 class="fw-bold mb-0 text-success mt-1">₹<?= number_format($sumNet, 2) ?></h4>
                    </div>
                    <div class="rounded-circle p-3 bg-success-subtle text-success">
                        <i class="bi bi-check-circle fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Invoices Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-receipt-cutoff text-primary fs-5"></i>
                <h6 class="mb-0 fw-bold text-dark">Purchase Invoices Directory</h6>
                <span class="badge bg-light text-dark border ms-1" id="billsBadgeCount"><?= $totalBills ?> Bills</span>
            </div>
            <!-- Search & Status Filter Controls -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="width: 220px;">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="billSearchInput" class="form-control border-start-0" placeholder="Search bill #, supplier, inv...">
                </div>
                <select id="billTypeFilter" class="form-select form-select-sm" style="width: 155px;">
                    <option value="">All Bill Types</option>
                    <option value="rolls">Roll</option>
                    <option value="garment_other">Other Garments Supply</option>
                    <option value="finished_goods">Finished Goods</option>
                </select>
                <select id="billPaymentFilter" class="form-select form-select-sm" style="width: 130px;">
                    <option value="">All Payments</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
                <select id="billInwardFilter" class="form-select form-select-sm" style="width: 130px;">
                    <option value="">All Inwards</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom" id="tablePurchaseBills">
                <thead class="table-light text-muted small">
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Bill No</th>
                        <th>Supplier</th>
                        <th>Supplier Inv #</th>
                        <th>Bill Date</th>
                        <th>Bill Type</th>
                        <th>Items</th>
                        <th>Taxable Amt</th>
                        <th>Tax</th>
                        <th>Net Bill Amount</th>
                        <th>Payment</th>
                        <th>Inward</th>
                        <th class="text-end pe-3" style="min-width: 130px;">Action</th>
                    </tr>
                </thead>
                <tbody id="billsTableBody">
                    <?php if (empty($bills)): ?>
                        <tr><td colspan="13" class="text-center py-4 text-muted">No purchase bills recorded. Click "Record Production Purchase" to create one.</td></tr>
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
                                <td class="text-muted small"><?= $bIdx++ ?></td>
                                <td>
                                    <a href="<?= \App\Core\Controller::url('/m/purchase_billing/view?id=' . $b['id']) ?>" class="badge bg-dark font-monospace text-decoration-none">
                                        <?= htmlspecialchars($b['bill_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($b['supplier_name']) ?></div>
                                    <?php if (!empty($b['concern_name']) && empty($selectedCid)): ?>
                                        <small class="text-muted"><?= htmlspecialchars($b['concern_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($b['supplier_invoice_no'])): ?>
                                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($b['supplier_invoice_no']) ?></span>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($b['bill_date'])) ?></td>
                                <td>
                                    <?php if (empty($types)): ?>
                                        <span class="text-muted small">-</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            <?php if (in_array('rolls', $types)): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace py-1 px-2" style="font-size: 0.72rem;">
                                                    <i class="bi bi-layers-fill me-1"></i>Roll
                                                </span>
                                            <?php endif; ?>
                                            <?php if (in_array('garment_other', $types)): ?>
                                                <span class="badge bg-info-subtle text-dark border border-info-subtle font-monospace py-1 px-2" style="font-size: 0.72rem;">
                                                    <i class="bi bi-scissors me-1"></i>Other Garments Supply
                                                </span>
                                            <?php endif; ?>
                                            <?php if (in_array('finished_goods', $types)): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace py-1 px-2" style="font-size: 0.72rem;">
                                                    <i class="bi bi-bag-check-fill me-1"></i>Finished Goods
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary border"><?= (int)($b['items_count'] ?? 1) ?> Items</span></td>
                                <td>₹<?= number_format($b['total_amount'], 2) ?></td>
                                <td>₹<?= number_format($b['tax_amount'], 2) ?></td>
                                <td class="fw-bold text-primary">₹<?= number_format($b['net_amount'], 2) ?></td>
                                <td>
                                    <?php if ($pmtStatus === 'paid'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Paid</span>
                                    <?php elseif ($pmtStatus === 'partial'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info">Partial</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning">Unpaid</span>
                                    <?php endif; ?>
                                    <?php if (!empty($b['payment_method'])): ?>
                                        <small class="text-muted d-block text-capitalize mt-1" style="font-size: 0.72rem;"><?= htmlspecialchars(str_replace('_', ' ', $b['payment_method'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inwStatus === 'completed'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <!-- CRUD Actions -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= \App\Core\Controller::url('/m/purchase_billing/view?id=' . $b['id']) ?>" class="btn btn-outline-primary py-1 px-2" title="View Full Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (\App\Core\Auth::can('purchase_billing', 'edit')): ?>
                                        <a href="<?= \App\Core\Controller::url('/m/purchase_billing/edit?id=' . $b['id']) ?>" class="btn btn-outline-secondary py-1 px-2" title="Edit Purchase Bill">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('purchase_billing', 'delete')): ?>
                                        <button type="button" class="btn btn-outline-danger py-1 px-2"
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
    const typeFilter = document.getElementById('billTypeFilter');
    const paymentFilter = document.getElementById('billPaymentFilter');
    const inwardFilter = document.getElementById('billInwardFilter');
    const rows = document.querySelectorAll('.bill-row');
    const badgeCount = document.getElementById('billsBadgeCount');

    function filterBills() {
        const query = (searchInput.value || '').trim().toLowerCase();
        const bType = (typeFilter ? typeFilter.value : '').trim().toLowerCase();
        const pmt = (paymentFilter.value || '').trim().toLowerCase();
        const inw = (inwardFilter.value || '').trim().toLowerCase();
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

        if (badgeCount) {
            badgeCount.textContent = visibleCount + ' Bills';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterBills);
    if (typeFilter) typeFilter.addEventListener('change', filterBills);
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
