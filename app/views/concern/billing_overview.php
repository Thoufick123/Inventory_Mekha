<?php
$billingUrl = \App\Core\Controller::url('/m/billing');
$createBillUrl = \App\Core\Controller::url('/m/billing/create');
$query = [
    'status' => $status,
    'channel' => $channel,
];
foreach ([
    'q' => $search,
    'from' => $dateFrom,
    'to' => $dateTo,
    'category_id' => $categoryId > 0 ? (string)$categoryId : '',
    'payment' => $payment,
    'gst' => $gst,
] as $key => $value) {
    if ($value !== '' && $value !== '0') {
        $query[$key] = $value;
    }
}
$pageUrl = static function (int $pageNumber) use ($billingUrl, $query): string {
    return $billingUrl . '?' . http_build_query($query + ['page' => $pageNumber]);
};
$totalRevenue = (float)($summary['total_revenue'] ?? 0);
$paidRevenue = (float)($summary['paid_revenue'] ?? 0);
$paymentMethodLabels = [
    'cash' => 'CASH',
    'upi' => 'UPI / QR',
    'card' => 'CARD / POS',
    'split' => 'CASH + UPI',
    'bank_transfer' => 'BANK TRANSFER',
    'credit' => 'CREDIT',
];
$formatPaymentMethod = static function ($method) use ($paymentMethodLabels): string {
    $key = strtolower(trim((string)$method));
    return $paymentMethodLabels[$key] ?? strtoupper(str_replace('_', ' ', $key));
};
?>

<div class="container-fluid px-0 pos-billing-overview">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="text-secondary small mb-1">
                <a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a>
                <span class="mx-1">/</span>
                <span class="text-muted">Shop</span>
                <span class="mx-1">/</span>
                <span class="text-dark fw-semibold">POS Billing</span>
            </div>
            <h3 class="fw-bold text-dark mb-1">POS Billing</h3>
            <div class="text-muted">Bills and payment collection overview</div>
        </div>
        <a href="<?= htmlspecialchars($createBillUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary px-4 py-2 fw-semibold">
            <i class="bi bi-plus-lg me-1"></i> Create Bill
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 pos-overview-stat">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-bold text-uppercase text-muted">Billed Revenue</div>
                        <div class="fs-4 fw-bold mt-2">₹<?= number_format($totalRevenue, 2) ?></div>
                        <div class="small text-muted mt-1">₹<?= number_format($paidRevenue, 2) ?> collected</div>
                    </div>
                    <span class="pos-overview-icon text-success bg-success-subtle"><i class="bi bi-currency-rupee"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 pos-overview-stat">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-bold text-uppercase text-muted">POS Invoices</div>
                        <div class="fs-4 fw-bold mt-2"><?= number_format((int)$summary['invoice_count']) ?></div>
                        <div class="small mt-1">
                            <span class="text-success"><?= number_format((int)$summary['paid_count']) ?> Paid</span>
                            <span class="text-muted mx-1">·</span>
                            <span class="text-warning"><?= number_format((int)$summary['pending_count']) ?> Unpaid</span>
                        </div>
                    </div>
                    <span class="pos-overview-icon text-primary bg-primary-subtle"><i class="bi bi-receipt"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 pos-overview-stat">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-bold text-uppercase text-muted">Billed Customers</div>
                        <div class="fs-4 fw-bold mt-2"><?= number_format((int)$summary['customer_count']) ?></div>
                        <div class="small text-muted mt-1">Unique POS customers</div>
                    </div>
                    <span class="pos-overview-icon text-info bg-info-subtle"><i class="bi bi-people"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 pos-overview-stat">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-bold text-uppercase text-muted">Collection Rate</div>
                        <div class="fs-4 fw-bold mt-2"><?= number_format((float)$collectionRate, 1) ?>%</div>
                        <div class="small text-muted mt-1">Paid revenue against billed revenue</div>
                    </div>
                    <span class="pos-overview-icon text-warning bg-warning-subtle"><i class="bi bi-check2-circle"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 pos-billing-channel-tabs">
        <?php
        $tabs = [
            'all' => ['All', (float)$summary['total_revenue'], (int)$summary['invoice_count'], 'bi-list-ul'],
            'cash' => ['Cash', (float)$summary['cash_revenue'], (int)$summary['cash_count'], 'bi-cash'],
            'online' => ['Online', (float)$summary['online_revenue'], (int)$summary['online_count'], 'bi-globe'],
            'direct' => ['Direct Bills', (float)$summary['direct_revenue'], (int)$summary['direct_count'], 'bi-building'],
        ];
        foreach ($tabs as $tabChannel => [$tabLabel, $tabRevenue, $tabCount, $tabIcon]):
            $tabQuery = $query;
            $tabQuery['channel'] = $tabChannel;
            unset($tabQuery['page']);
            $tabUrl = $billingUrl . '?' . http_build_query($tabQuery);
        ?>
            <a href="<?= htmlspecialchars($tabUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn <?= $channel === $tabChannel ? 'btn-primary' : 'btn-light border' ?> px-3 d-inline-flex align-items-center gap-2">
                <i class="bi <?= htmlspecialchars($tabIcon, ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                <span><?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="small fw-semibold">₹<?= number_format($tabRevenue, 2) ?></span>
                <span class="small opacity-75"><?= number_format($tabCount) ?> invoices</span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-receipt-cutoff text-primary me-2"></i><?= $channel === 'direct' ? 'Direct Bills' : 'POS Invoices &amp; Bills' ?></h5>
            <span class="badge rounded-pill bg-primary-subtle text-primary">Showing <?= number_format(count($invoices)) ?> of <?= number_format($totalInvoices) ?> bills</span>
        </div>

        <div class="card-body border-bottom p-3">
            <form method="GET" action="<?= htmlspecialchars($billingUrl, ENT_QUOTES, 'UTF-8') ?>" class="pos-billing-filter">
                <input type="hidden" name="channel" value="<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-2">
                    <div class="col-12 col-xl-4">
                        <label for="pos_bill_search" class="visually-hidden">Search invoices</label>
                        <input type="search" id="pos_bill_search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Search product, SKU, invoice, customer..." aria-label="Search POS bills">
                    </div>
                    <div class="col-6 col-xl-2">
                        <label for="pos_bill_date_from" class="visually-hidden">From date</label>
                        <input type="date" id="pos_bill_date_from" name="from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" class="form-control" aria-label="From date">
                    </div>
                    <div class="col-6 col-xl-2">
                        <label for="pos_bill_date_to" class="visually-hidden">To date</label>
                        <input type="date" id="pos_bill_date_to" name="to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" class="form-control" aria-label="To date">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="pos_bill_category" class="visually-hidden">Category</label>
                        <select id="pos_bill_category" name="category_id" class="form-select" aria-label="Category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-1 d-grid">
                        <button type="submit" class="btn btn-primary" aria-label="Apply filters" title="Apply filters"><i class="bi bi-funnel-fill"></i></button>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="pos_bill_payment" class="visually-hidden">Payment or online mode</label>
                        <select id="pos_bill_payment" name="payment" class="form-select" aria-label="Payment or online mode">
                            <option value="">All payment / online modes</option>
                            <option value="cash" <?= $payment === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="upi" <?= $payment === 'upi' ? 'selected' : '' ?>>UPI / QR</option>
                            <option value="card" <?= $payment === 'card' ? 'selected' : '' ?>>Card / POS</option>
                            <option value="split" <?= $payment === 'split' ? 'selected' : '' ?>>Cash + UPI</option>
                            <option value="bank_transfer" <?= $payment === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="credit" <?= $payment === 'credit' ? 'selected' : '' ?>>Credit</option>
                            <option value="online" <?= $payment === 'online' ? 'selected' : '' ?>>Online Channel</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="pos_bill_gst" class="visually-hidden">GST method</label>
                        <select id="pos_bill_gst" name="gst" class="form-select" aria-label="GST method">
                            <option value="">All GST methods</option>
                            <option value="both" <?= $gst === 'both' ? 'selected' : '' ?>>Both CGST &amp; SGST</option>
                            <option value="igst" <?= $gst === 'igst' ? 'selected' : '' ?>>IGST</option>
                            <option value="exempt" <?= $gst === 'exempt' ? 'selected' : '' ?>>Exempt</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="pos_bill_status" class="visually-hidden">Payment status</label>
                        <select id="pos_bill_status" name="status" class="form-select" aria-label="Payment status">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All payment statuses</option>
                            <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid Bills</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Bills</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <a href="<?= htmlspecialchars($billingUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-link text-muted">Clear filters</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-uppercase small text-muted ps-3">Bill No.</th>
                        <th class="text-uppercase small text-muted">Date</th>
                        <th class="text-uppercase small text-muted">Bill To</th>
                        <th class="text-uppercase small text-muted text-center">Items</th>
                        <th class="text-uppercase small text-muted text-end">Total</th>
                        <th class="text-uppercase small text-muted">Payment</th>
                        <th class="text-uppercase small text-muted">Status</th>
                        <th class="text-uppercase small text-muted text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$invoices): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <?= $hasFilters ? 'No bills match the selected filters.' : 'No POS bills have been created.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <?php
                            $paymentStatus = strtolower((string)($invoice['payment_status'] ?? 'unpaid'));
                            $statusClass = $paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'partial' ? 'warning' : 'secondary');
                            ?>
                            <tr>
                                <td class="ps-3 fw-semibold"><?= htmlspecialchars($invoice['invoice_no'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-muted"><?= htmlspecialchars(date('d M Y', strtotime($invoice['invoice_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($invoice['customer_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($invoice['customer_phone'] ?: '-', ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php if (!empty($invoice['is_direct_bill'])): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis ms-1">Direct</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= number_format((int)$invoice['items_count']) ?></td>
                                <td class="text-end fw-bold">₹<?= number_format((float)$invoice['net_amount'], 2) ?></td>
                                <td><span class="badge bg-primary-subtle text-primary text-uppercase"><?= htmlspecialchars($formatPaymentMethod($invoice['payment_method']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="badge bg-<?= $statusClass ?>-subtle text-<?= $statusClass ?> text-uppercase"><?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="text-end pe-3">
                                    <a href="<?= htmlspecialchars(\App\Core\Controller::url('/m/billing/create?print_id=' . (int)$invoice['id']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary" title="Open and print bill">
                                        <i class="bi bi-printer"></i><span class="d-none d-lg-inline ms-1">Print</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small text-muted">Page <?= number_format($page) ?> of <?= number_format($totalPages) ?> · <?= number_format($totalInvoices) ?> bills</span>
                <div class="btn-group">
                    <a href="<?= htmlspecialchars($pageUrl(max(1, $page - 1)), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="Previous page">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <a href="<?= htmlspecialchars($pageUrl(min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" aria-label="Next page">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.pos-overview-stat {
    border-radius: 0.8rem;
    padding: 1.15rem;
}
.pos-overview-icon {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 1.1rem;
}
.pos-billing-channel-tabs {
    overflow-x: auto;
    flex-wrap: nowrap !important;
}
.pos-billing-channel-tabs .btn {
    white-space: nowrap;
}
.pos-billing-filter input,
.pos-billing-filter select {
    min-width: 0;
}
</style>
