<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <ul class="nav nav-pills bg-light p-1 rounded-3 border d-inline-flex mb-0" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link py-1 px-3 rounded-2 fw-semibold small <?= (($activeTab ?? 'purchases') === 'purchases') ? 'active shadow-sm' : 'text-secondary' ?>" id="tab-purchases-btn" data-bs-toggle="pill" data-bs-target="#tab-purchases" type="button" role="tab">
                    <i class="bi bi-receipt me-1"></i> Product Purchases (<?= count($bills) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-1 px-3 rounded-2 fw-semibold small <?= (($activeTab ?? 'purchases') === 'hierarchy') ? 'active shadow-sm' : 'text-secondary' ?>" id="tab-pa-hierarchy-btn" data-bs-toggle="pill" data-bs-target="#tab-pa-hierarchy" type="button" role="tab">
                    <i class="bi bi-diagram-3 me-1"></i> 3-Tier Hierarchy &amp; Attributes
                </button>
            </li>
        </ul>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <?php
            $exportUrl = \App\Core\Controller::url('/m/product_adding/export');
            $currentModule = 'product_adding';
            $prefix = 'pa_exp';
            include dirname(__DIR__) . '/partials/export_card.php';
            ?>
            <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('product_adding', 'add')): ?>
                <a href="<?= $createUrl ?>" class="btn text-white shadow-sm px-3 py-2 fw-semibold rounded-2 d-flex align-items-center gap-2" style="background:#4f46e5;">
                    <i class="bi bi-bag-plus"></i> Create Product Purchase
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-check-circle-fill fs-5 me-2"></i>
            <div><?= htmlspecialchars($flashSuccess) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
            <div><?= htmlspecialchars($flashError) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="tab-content">
        <div class="tab-pane fade <?= (($activeTab ?? 'purchases') === 'purchases') ? 'show active' : '' ?>" id="tab-purchases" role="tabpanel">

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-primary">
                <div class="card-body p-3 d-flex justify-content-between align-items-center gap-2">
                    <div class="min-w-0">
                        <div class="text-uppercase text-muted fw-bold mb-1 text-truncate" style="font-size: 0.68rem; letter-spacing: 0.04em;">Purchase Bills</div>
                        <div class="fs-4 fw-bold text-dark lh-1"><?= number_format((int)($summary['total_bills'] ?? 0)) ?></div>
                    </div>
                    <span class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;"><i class="bi bi-receipt fs-5"></i></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-info">
                <div class="card-body p-3 d-flex justify-content-between align-items-center gap-2">
                    <div class="min-w-0">
                        <div class="text-uppercase text-muted fw-bold mb-1 text-truncate" style="font-size: 0.68rem; letter-spacing: 0.04em;">Bill Lines</div>
                        <div class="fs-4 fw-bold text-dark lh-1"><?= number_format((int)($summary['total_items'] ?? 0)) ?></div>
                    </div>
                    <span class="rounded-3 bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;"><i class="bi bi-list-ul fs-5"></i></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-secondary">
                <div class="card-body p-3 d-flex justify-content-between align-items-center gap-2">
                    <div class="min-w-0">
                        <div class="text-uppercase text-muted fw-bold mb-1 text-truncate" style="font-size: 0.68rem; letter-spacing: 0.04em;">Total GST</div>
                        <div class="fs-5 fw-bold text-dark lh-1">₹<?= number_format((float)($summary['total_tax'] ?? 0), 2) ?></div>
                    </div>
                    <span class="rounded-3 bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;"><i class="bi bi-percent fs-5"></i></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-success">
                <div class="card-body p-3 d-flex justify-content-between align-items-center gap-2">
                    <div class="min-w-0">
                        <div class="text-uppercase text-muted fw-bold mb-1 text-truncate" style="font-size: 0.68rem; letter-spacing: 0.04em;">Net Purchases</div>
                        <div class="fs-5 fw-bold text-success lh-1">₹<?= number_format((float)($summary['total_net'] ?? 0), 2) ?></div>
                    </div>
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;"><i class="bi bi-wallet2 fs-5"></i></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-warning">
                <div class="card-body p-3 d-flex justify-content-between align-items-center gap-2">
                    <div class="min-w-0">
                        <div class="text-uppercase text-muted fw-bold mb-1 text-truncate" style="font-size: 0.68rem; letter-spacing: 0.04em;">Pending Stock Bills</div>
                        <div class="fs-4 fw-bold text-warning lh-1"><?= number_format((int)($summary['pending_bills'] ?? 0)) ?></div>
                    </div>
                    <span class="rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;"><i class="bi bi-box-seam fs-5"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-journal-text text-primary fs-5"></i>
                <h6 class="mb-0 fw-bold">Product Purchase Bills &amp; Eligible Purchase Billing Invoices</h6>
                <span class="badge bg-light text-dark border"><?= count($bills) ?> bills</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-muted small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                    <tr>
                        <th class="ps-3" style="width: 38px;">#</th>
                        <th>Invoice #</th>
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
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr>
                            <td colspan="<?= empty($selectedCid) ? '13' : '12' ?>" class="text-center py-5">
                                <div class="text-muted mb-3">No eligible purchase invoices found.</div>
                                <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('product_adding', 'add')): ?>
                                    <a href="<?= $createUrl ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create the first purchase</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $paIdx = 1; foreach ($bills as $bill): ?>
                            <?php
                            $paymentStatus = strtolower((string)($bill['payment_status'] ?? 'unpaid'));
                            $inwardStatus = strtolower((string)($bill['inward_status'] ?? 'pending'));
                            ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $paIdx++ ?></td>
                                <td><span class="font-monospace fw-semibold"><?= htmlspecialchars($bill['supplier_invoice_no'] ?: '-') ?></span></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($bill['supplier_name'] ?? 'Supplier') ?></div>
                                    <?php if (!empty($bill['supplier_company'])): ?>
                                        <div class="text-muted" style="font-size: 0.74rem;"><?= htmlspecialchars($bill['supplier_company']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if (empty($selectedCid)): ?>
                                    <td><?= htmlspecialchars($bill['concern_name'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td class="text-nowrap"><?= htmlspecialchars(date('d M Y', strtotime((string)$bill['bill_date']))) ?></td>
                                <td class="text-end"><?= number_format((int)$bill['item_count']) ?></td>
                                <td class="text-end"><?= number_format((float)$bill['total_qty'], 2) ?></td>
                                <td class="text-end text-nowrap">₹<?= number_format((float)$bill['total_amount'], 2) ?></td>
                                <td class="text-end text-nowrap">₹<?= number_format((float)$bill['tax_amount'], 2) ?></td>
                                <td class="text-end fw-bold text-primary text-nowrap">₹<?= number_format((float)$bill['net_amount'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $paymentStatus === 'paid' ? 'text-bg-success' : ($paymentStatus === 'partial' ? 'text-bg-info' : 'text-bg-warning') ?>">
                                        <?= htmlspecialchars(ucfirst($paymentStatus)) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= htmlspecialchars($bill['sync_badge_class'] ?? ($inwardStatus === 'completed' ? 'text-bg-success' : 'text-bg-warning')) ?>">
                                        <?php if (!empty($bill['qty_mismatch'])): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?>
                                        <?= htmlspecialchars($bill['sync_label'] ?? ($inwardStatus === 'completed' ? 'Completed' : ((int)$bill['stocked_count'] . ' / ' . (int)$bill['item_count'] . ' lines'))) ?>
                                    </span>
                                    <div class="text-muted mt-1" style="font-size: 0.73rem;"><?= (int)$bill['stocked_count'] ?> / <?= (int)$bill['item_count'] ?> stocked</div>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1 flex-nowrap">
                                        <a href="<?= \App\Core\Controller::url('/m/purchase_billing/view?id=' . (int)$bill['id']) ?>" class="btn btn-sm btn-outline-info text-nowrap" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (($bill['source_module'] ?? '') === 'purchase_billing'): ?>
                                            <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('product_adding', 'edit')): ?>
                                                <a href="<?= \App\Core\Controller::url('/m/product_adding/edit_purchase_bill?id=' . (int)$bill['id']) ?>" class="btn btn-sm btn-outline-primary text-nowrap" title="Edit Invoice">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </a>
                                            <?php endif; ?>
                                            <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('product_adding', 'add')): ?>
                                                <form action="<?= \App\Core\Controller::url('/m/product_adding/add_purchase_bill_to_stock') ?>" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                    <input type="hidden" name="bill_id" value="<?= (int)$bill['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success text-nowrap" title="Add Invoice to Stock">
                                                        <i class="bi bi-box-arrow-in-down me-1"></i>Add Stock
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="<?= \App\Core\Controller::url('/m/product_adding?bill_id=' . (int)$bill['id']) ?>" class="btn btn-sm btn-outline-primary text-nowrap" title="Labels & Stock">
                                                <i class="bi bi-upc-scan me-1"></i>Labels &amp; Stock
                                            </a>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::isSuperAdmin() || \App\Core\Auth::can('product_adding', 'delete')): ?>
                                            <form action="<?= \App\Core\Controller::url('/m/product_adding/delete_purchase_bill') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this purchase bill? Any unused finished-goods stock will be reversed. Deletion is blocked if stock has been used, allocated to production, or damaged.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                                                <input type="hidden" name="bill_id" value="<?= (int)$bill['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap" title="Delete Purchase Bill">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
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
        </div><!-- /#tab-purchases -->

        <div class="tab-pane fade <?= (($activeTab ?? 'purchases') === 'hierarchy') ? 'show active' : '' ?>" id="tab-pa-hierarchy" role="tabpanel">
            <?php
                $hierarchyPermissionModule = 'product_adding';
                $hierarchyReturnUrl = '/m/product_adding?tab=hierarchy';
                $hierarchyRoutePrefix = '/m/product_adding';
                require __DIR__ . '/partials/hierarchy_attributes_hsn.php';
            ?>
        </div>
    </div>
</div>
