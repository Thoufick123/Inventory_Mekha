<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Product Adding</h4>
            <p class="text-secondary small mb-0">Review product purchase bills, totals, payment status, and stock progress.</p>
        </div>
        <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('product_adding', 'add')): ?>
            <a href="<?= $createUrl ?>" class="btn text-white shadow-sm px-3 py-2 fw-semibold rounded-2 d-flex align-items-center gap-2" style="background:#4f46e5;">
                <i class="bi bi-bag-plus"></i>Create Product Purchase
            </a>
        <?php endif; ?>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-4">
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Product Bills</div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format((int)($summary['total_bills'] ?? 0)) ?></div>
                    </div>
                    <span class="rounded-circle p-3 bg-primary-subtle text-primary"><i class="bi bi-receipt"></i></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Bill Lines</div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format((int)($summary['total_items'] ?? 0)) ?></div>
                    </div>
                    <span class="rounded-circle p-3 bg-info-subtle text-info"><i class="bi bi-list-ul"></i></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Total GST</div>
                        <div class="fs-5 fw-bold text-dark">₹<?= number_format((float)($summary['total_tax'] ?? 0), 2) ?></div>
                    </div>
                    <span class="rounded-circle p-3 bg-warning-subtle text-warning"><i class="bi bi-percent"></i></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Net Purchases</div>
                        <div class="fs-5 fw-bold text-success">₹<?= number_format((float)($summary['total_net'] ?? 0), 2) ?></div>
                    </div>
                    <span class="rounded-circle p-3 bg-success-subtle text-success"><i class="bi bi-wallet2"></i></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Pending Stock Bills</div>
                        <div class="fs-4 fw-bold text-warning"><?= number_format((int)($summary['pending_bills'] ?? 0)) ?></div>
                    </div>
                    <span class="rounded-circle p-3 bg-warning-subtle text-warning"><i class="bi bi-box-seam"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-journal-text text-primary fs-5"></i>
                <h6 class="mb-0 fw-bold">Product Purchase Bills</h6>
                <span class="badge bg-light text-dark border"><?= count($bills) ?> bills</span>
            </div>
            <span class="small text-muted">Open a bill to print labels and add selected lines to stock.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small">
                    <tr>
                        <th>Bill No</th>
                        <th>Supplier Invoice</th>
                        <th>Supplier</th>
                        <?php if (empty($selectedCid)): ?><th>Concern</th><?php endif; ?>
                        <th>Bill Date</th>
                        <th class="text-end">Lines</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Taxable</th>
                        <th class="text-end">GST</th>
                        <th class="text-end">Net Amount</th>
                        <th>Payment</th>
                        <th>Stock</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr>
                            <td colspan="<?= empty($selectedCid) ? '13' : '12' ?>" class="text-center py-5">
                                <div class="text-muted mb-3">No Product Adding bills yet.</div>
                                <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('product_adding', 'add')): ?>
                                    <a href="<?= $createUrl ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create the first purchase</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bills as $bill): ?>
                            <?php
                            $paymentStatus = strtolower((string)($bill['payment_status'] ?? 'unpaid'));
                            $inwardStatus = strtolower((string)($bill['inward_status'] ?? 'pending'));
                            ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($bill['bill_no']) ?></span></td>
                                <td><span class="font-monospace"><?= htmlspecialchars($bill['supplier_invoice_no'] ?: '-') ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($bill['supplier_name'] ?? 'Supplier') ?></div>
                                    <?php if (!empty($bill['supplier_company'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($bill['supplier_company']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if (empty($selectedCid)): ?>
                                    <td><?= htmlspecialchars($bill['concern_name'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars(date('d M Y', strtotime((string)$bill['bill_date']))) ?></td>
                                <td class="text-end"><?= number_format((int)$bill['item_count']) ?></td>
                                <td class="text-end"><?= number_format((float)$bill['total_qty'], 2) ?></td>
                                <td class="text-end">₹<?= number_format((float)$bill['total_amount'], 2) ?></td>
                                <td class="text-end">₹<?= number_format((float)$bill['tax_amount'], 2) ?></td>
                                <td class="text-end fw-bold text-primary">₹<?= number_format((float)$bill['net_amount'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $paymentStatus === 'paid' ? 'text-bg-success' : ($paymentStatus === 'partial' ? 'text-bg-info' : 'text-bg-warning') ?>">
                                        <?= htmlspecialchars(ucfirst($paymentStatus)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($inwardStatus === 'completed'): ?>
                                        <span class="badge text-bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning"><?= (int)$bill['stocked_count'] ?> / <?= (int)$bill['item_count'] ?> lines</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= \App\Core\Controller::url('/m/product_adding?bill_id=' . (int)$bill['id']) ?>" class="btn btn-sm btn-outline-primary text-nowrap">
                                        <i class="bi bi-eye me-1"></i>Labels &amp; Stock
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
