<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Shop Returns &amp; Exchanges</h4>
            <p class="text-secondary small mb-0">Find a customer invoice, select returned items, and process a refund or exchange.</p>
        </div>
        <a href="#returnTransactions" class="btn btn-outline-success rounded-pill">
            <i class="bi bi-box-seam me-1"></i> Returned Products
        </a>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Customer Returns & Exchanges (Excel & PDF)';
    $exportSubtitle = 'Download returned units, replacement variant exchanges, refunds & restock audit journals';
    $exportUrl = \App\Core\Controller::url('/m/return_exchange/export');
    $prefix = 'retexc_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <?php if (\App\Core\Auth::can('return_exchange', 'add')): ?>
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3 p-md-4">
            <label for="shop_return_phone" class="form-label fw-semibold small mb-2">
                <i class="bi bi-search text-success me-1"></i> Search Shop Customer Order
            </label>
            <form id="shopReturnLookupForm" class="input-group shop-return-lookup">
                <span class="input-group-text bg-white"><i class="bi bi-qr-code text-success"></i></span>
                <input type="tel" id="shop_return_phone" class="form-control" placeholder="Enter customer phone number" autocomplete="tel" required>
                <button type="submit" class="btn btn-success px-4 fw-semibold" id="shop_return_lookup_button">
                    <i class="bi bi-search me-1"></i> Lookup Order
                </button>
            </form>
            <div id="shop_return_lookup_status" class="small mt-2" role="status" aria-live="polite"></div>
            <div id="shop_return_invoice_list" class="list-group mt-2 d-none" aria-label="Customer invoices"></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div id="returnTransactions"></div>
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-counterclockwise me-2 text-warning"></i> Return Transactions</h6>
            <span class="badge bg-light text-dark border"><?= count($returns) ?> Records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Return No</th>
                        <th>Invoice</th>
                        <th>Type</th>
                        <th>Customer</th>
                        <th>Returned Variant</th>
                        <th>Restocked (+Qty)</th>
                        <th>Exchange Replacement</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">No returns or exchanges recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $r): ?>
                            <?php $invoiceReturn = !empty($r['sales_invoice_id']) || !empty($r['production_return_id']); ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($r['return_no']) ?></span></td>
                                <td class="font-monospace small"><?= htmlspecialchars($r['invoice_no'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge <?= $r['type'] === 'exchange' ? 'bg-info text-dark' : 'bg-secondary' ?>">
                                        <?= strtoupper($r['type']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($r['customer_name']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($r['returned_variant_name']) ?></div>
                                    <small class="badge bg-light text-dark border font-monospace">SKU: <?= htmlspecialchars($r['returned_sku']) ?></small>
                                </td>
                                <td><span class="text-success fw-bold fs-6">+<?= number_format($r['returned_qty'], 2) ?></span></td>
                                <td>
                                    <?php if ($r['type'] === 'exchange' && $r['exchange_variant_name']): ?>
                                        <div class="text-dark small fw-semibold"><?= htmlspecialchars($r['exchange_variant_name']) ?></div>
                                        <small class="badge bg-light text-dark border font-monospace">SKU: <?= htmlspecialchars($r['exchange_sku'] ?: '-') ?></small>
                                        <small class="text-danger fw-bold">-<?= number_format($r['exchange_qty'], 2) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted small">None (Pure Return)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($r['reason'] ?: '-') ?></td>
                                <td><?= date('d M Y', strtotime($r['return_date'])) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewReturnModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Return Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (!$invoiceReturn): ?>
                                            <?php if (\App\Core\Auth::can('return_exchange', 'edit')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditReturnModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Return">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (\App\Core\Auth::can('return_exchange', 'delete')): ?>
                                            <form action="<?= \App\Core\Controller::url('/m/return_exchange/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete return record <?= htmlspecialchars($r['return_no']) ?>? This will reverse stock additions/deductions.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Return">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">Invoice used</span>
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

<?php if (\App\Core\Auth::can('return_exchange', 'add')): ?>
<div class="modal fade" id="modalShopReturn" tabindex="-1" aria-labelledby="modalShopReturnTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form action="<?= \App\Core\Controller::url('/production/returns/store') ?>" method="POST" id="shopReturnForm" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="source_workflow" value="shop">
            <input type="hidden" name="concern_id" id="shop_return_concern_id">
            <input type="hidden" name="sales_invoice_id" id="shop_return_invoice_id">
            <input type="hidden" name="customer_id" id="shop_return_customer_id">
            <input type="hidden" name="return_date" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="type" id="shop_return_type" value="RETURN">
            <input type="hidden" name="settlement_mode" id="shop_return_settlement_mode" value="Cash">
            <input type="hidden" name="in_items" id="shop_return_in_items" value="[]">
            <input type="hidden" name="out_items" id="shop_return_out_items" value="[]">

            <div class="modal-header border-bottom py-2">
                <div>
                    <div class="small text-muted"><span class="badge bg-success me-1">SHOP RETAIL POS</span> Return &amp; Exchange Portal</div>
                    <h5 class="modal-title fw-bold mb-0" id="modalShopReturnTitle">Step 2: Select Items &amp; Action Type</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 align-items-center border rounded-3 p-2 mb-3 bg-light">
                    <div class="col-6 col-md-3"><small class="text-muted d-block">INVOICE NUMBER</small><strong class="font-monospace" id="shop_return_summary_invoice">-</strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">CUSTOMER NAME</small><strong id="shop_return_summary_customer">-</strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">ORDER DATE</small><strong id="shop_return_summary_date">-</strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">TOTAL ORDER VALUE</small><strong class="text-success" id="shop_return_summary_total">₹0.00</strong></div>
                </div>

                <div class="small fw-bold text-uppercase text-muted mb-2">Choose Action Type for Selected Items</div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <button type="button" class="shop-return-action active w-100 text-start p-3 rounded-3" data-return-type="RETURN">
                            <strong><i class="bi bi-arrow-return-left me-2"></i>Direct Return</strong>
                            <span class="badge bg-danger-subtle text-danger float-end">Refund</span>
                            <small class="d-block text-muted mt-1">Customer returns items for a direct refund.</small>
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="shop-return-action w-100 text-start p-3 rounded-3" data-return-type="EXCHANGE">
                            <strong><i class="bi bi-arrow-left-right me-2"></i>Exchanged Product</strong>
                            <span class="badge bg-success-subtle text-success float-end">Any Product</span>
                            <small class="d-block text-muted mt-1">Exchange for any eligible product in shop stock.</small>
                        </button>
                    </div>
                </div>

                <section id="shop_return_payment_section" class="border rounded-3 p-3 mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2">Payment Method</div>
                    <div class="row row-cols-2 row-cols-md-4 g-2 mb-2">
                        <div class="col"><button type="button" class="shop-return-payment active w-100 p-2 rounded-2" data-settlement="Cash"><i class="bi bi-cash d-block fs-5"></i>Cash</button></div>
                        <div class="col"><button type="button" class="shop-return-payment w-100 p-2 rounded-2" data-settlement="UPI / QR"><i class="bi bi-qr-code d-block fs-5"></i>UPI / QR</button></div>
                        <div class="col"><button type="button" class="shop-return-payment w-100 p-2 rounded-2" data-settlement="Card / POS"><i class="bi bi-credit-card d-block fs-5"></i>Card / POS</button></div>
                        <div class="col"><button type="button" class="shop-return-payment w-100 p-2 rounded-2" data-settlement="Cash + UPI"><i class="bi bi-pie-chart d-block fs-5"></i>Cash + UPI</button></div>
                    </div>
                </section>
                <div class="mb-3">
                    <label for="shop_return_reason" class="form-label small fw-semibold mb-1">Reason / Notes</label>
                    <input type="text" id="shop_return_reason" name="reason" class="form-control form-control-sm" placeholder="e.g. Fitting issue, defect, customer requested refund..." required>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div><span class="badge bg-danger-subtle text-danger">STEP 2A: RETURNING OLD ITEMS</span> <small class="text-muted ms-2" id="shop_return_selected_count">0 of 0 items selected</small></div>
                    <div class="input-group input-group-sm shop-return-scan">
                        <input type="search" id="shop_return_item_search" class="form-control" placeholder="Scan a piece tag or SKU from invoice" aria-label="Find an invoice item">
                        <button type="button" class="btn btn-danger" id="shop_return_scan_item">Scan</button>
                    </div>
                </div>
                <div class="table-responsive border rounded-3 mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Old Product Item &amp; Piece Tags</th><th class="text-center">Sold</th><th class="text-center">Return Qty</th><th>Condition</th><th class="text-end">Unit Rate</th><th class="text-end">Line Credit</th></tr>
                        </thead>
                        <tbody id="shop_return_invoice_items">
                            <tr><td colspan="6" class="text-center text-muted py-3">Select an invoice to load its products.</td></tr>
                        </tbody>
                    </table>
                </div>

                <section id="shop_return_replacement_section" class="d-none">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <span class="badge bg-success-subtle text-success">STEP 2B: ADD REPLACEMENT PRODUCTS</span>
                        <div id="shop_return_replacement_form" class="input-group input-group-sm shop-return-scan">
                            <input type="search" id="shop_return_replacement_sku" class="form-control" placeholder="Scan replacement product SKU or QR tag" aria-label="Replacement product SKU or QR tag">
                            <button type="button" class="btn btn-success" id="shop_return_add_replacement">Add Product</button>
                        </div>
                    </div>
                    <div class="table-responsive border rounded-3 mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Replacement Product</th><th class="text-center">Available</th><th class="text-center">Qty</th><th class="text-end">Unit Rate</th><th class="text-end">Line Total</th><th></th></tr></thead>
                            <tbody id="shop_return_replacement_items">
                                <tr><td colspan="6" class="text-center text-muted py-3">Scan a replacement product from shop stock.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between small mb-1"><span>Return Product Paid Amount</span><strong id="shop_return_paid_total">₹0.00</strong></div>
                    <div class="d-flex justify-content-between small mb-1"><span>Current Choose Product Amount</span><strong id="shop_return_replacement_total">₹0.00</strong></div>
                    <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                        <strong>Total Pay Amount</strong>
                        <strong id="shop_return_balance" class="text-success">Even Swap (₹0.00 − ₹0.00)</strong>
                    </div>
                </div>
                <div id="shop_return_form_error" class="alert alert-danger py-2 mt-3 d-none" role="alert"></div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4" id="shop_return_process_button">
                    <i class="bi bi-check2-circle me-1"></i> Process Shop Return / Exchange
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (\App\Core\Auth::can('return_exchange', 'edit')): ?>
<!-- Modal Edit Return -->
<div class="modal fade" id="modalEditReturn" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/return_exchange/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_return_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Return / Exchange Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Return No</label>
                    <input type="text" id="edit_return_no" class="form-control font-monospace" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Return Date *</label>
                    <input type="date" name="return_date" id="edit_return_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Return Reason / Notes</label>
                    <textarea name="reason" id="edit_return_reason" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Return Log</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal View Return -->
<div class="modal fade" id="modalViewReturn" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-arrow-return-left"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Return / Replacement Exchange</h6>
                        <small class="badge bg-dark font-monospace" id="view_return_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Transaction Type</span>
                        <span class="badge" id="view_return_type_badge">RETURN</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Returned Qty Restocked</span>
                        <span class="fw-bold fs-5 text-success font-monospace" id="view_return_qty">+0.00</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-person text-primary me-2"></i>Customer:</span>
                        <strong class="text-dark" id="view_return_customer">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-box-arrow-in-down text-primary me-2"></i>Returned Item:</span>
                        <strong class="text-dark" id="view_return_variant">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-upc text-primary me-2"></i>Returned SKU:</span>
                        <span class="badge bg-light text-dark border font-monospace" id="view_return_sku">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between" id="view_exchange_row" style="display: none;">
                        <span class="text-muted"><i class="bi bi-arrow-repeat text-primary me-2"></i>Exchange Replacement:</span>
                        <span class="text-danger fw-semibold" id="view_exchange_info">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-date text-primary me-2"></i>Return Date:</span>
                        <span class="font-monospace text-dark" id="view_return_date">-</span>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1"><i class="bi bi-chat-left-dots text-primary me-2"></i>Reason:</span>
                        <div class="text-dark p-2 bg-light rounded" id="view_return_reason">-</div>
                    </li>
                </ul>
            </div>
            <div class="modal-footer border-top px-4 py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lookupForm = document.getElementById('shopReturnLookupForm');
    const lookupButton = document.getElementById('shop_return_lookup_button');
    const lookupStatus = document.getElementById('shop_return_lookup_status');
    const invoiceList = document.getElementById('shop_return_invoice_list');
    const returnForm = document.getElementById('shopReturnForm');
    const invoiceItemsBody = document.getElementById('shop_return_invoice_items');
    const replacementItemsBody = document.getElementById('shop_return_replacement_items');
    const replacementForm = document.getElementById('shop_return_replacement_form');
    const replacementSkuInput = document.getElementById('shop_return_replacement_sku');
    const replacementItems = [];
    let activeInvoice = null;
    let invoiceItems = [];
    let returnType = 'RETURN';
    const customerInvoicesUrl = <?= json_encode(\App\Core\Controller::url('/api/shop-returns/customer-invoices')) ?>;
    const invoiceDetailsUrl = <?= json_encode(\App\Core\Controller::url('/api/shop-returns/invoice')) ?>;
    const replacementLookupUrl = <?= json_encode(\App\Core\Controller::url('/api/shop-returns/replacement')) ?>;

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function formatMoney(value) {
        return '₹' + (Number(value) || 0).toFixed(2);
    }

    function showFormError(message) {
        const error = document.getElementById('shop_return_form_error');
        error.textContent = message;
        error.classList.remove('d-none');
    }

    function clearFormError() {
        const error = document.getElementById('shop_return_form_error');
        error.textContent = '';
        error.classList.add('d-none');
    }

    function renderInvoices(invoices) {
        invoiceList.innerHTML = '';
        if (!invoices.length) {
            invoiceList.classList.add('d-none');
            lookupStatus.innerHTML = '<span class="text-warning">No invoices found for this customer phone number.</span>';
            return;
        }

        lookupStatus.innerHTML = '<span class="text-primary fw-semibold">This customer has ' + invoices.length + ' invoice(s). Select a bill to continue.</span>';
        invoices.forEach(function(invoice) {
            const alreadyUsed = Number(invoice.already_used) === 1;
            const date = String(invoice.created_at || invoice.invoice_date || '').replace('T', ' ');
            const button = alreadyUsed
                ? '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Already Used</button>'
                : '<button type="button" class="btn btn-sm btn-outline-success" data-select-invoice="' + Number(invoice.invoice_id) + '">Select Bill</button>';
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2';
            item.innerHTML =
                '<div class="font-monospace fw-semibold text-primary">' + escapeHtml(invoice.invoice_no) + '</div>' +
                '<div class="small text-muted flex-grow-1 text-md-end">' +
                    escapeHtml(invoice.customer_name) + ' · ' + escapeHtml(date) +
                    (invoice.concern_name ? ' · ' + escapeHtml(invoice.concern_name) : '') +
                '</div>' +
                '<strong class="text-success">' + formatMoney(invoice.net_amount) + '</strong>' +
                button;
            invoiceList.appendChild(item);
        });
        invoiceList.classList.remove('d-none');
    }

    async function lookupCustomerInvoices(event) {
        if (event) event.preventDefault();
        const phone = document.getElementById('shop_return_phone').value.trim();
        if (!phone) return;

        lookupButton.disabled = true;
        lookupStatus.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-repeat me-1"></i>Searching customer invoices...</span>';
        invoiceList.classList.add('d-none');
        try {
            const response = await fetch(customerInvoicesUrl + '?phone=' + encodeURIComponent(phone), {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Invoice lookup failed.');
            renderInvoices(data.invoices || []);
        } catch (error) {
            lookupStatus.innerHTML = '<span class="text-danger">' + escapeHtml(error.message) + '</span>';
        } finally {
            lookupButton.disabled = false;
        }
    }

    function renderInvoiceItems() {
        if (!invoiceItems.length) {
            invoiceItemsBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">This invoice has no item lines.</td></tr>';
            return;
        }
        invoiceItemsBody.innerHTML = invoiceItems.map(function(item, index) {
            const pieceTag = item.tag_code ? String(item.tag_code) : '';
            const itemCode = pieceTag ? 'Piece Tag: ' + pieceTag : String(item.sku_code || '');
            const label = escapeHtml(itemCode) + ' · ' + escapeHtml(item.product_name) +
                (item.size ? ' · Size: ' + escapeHtml(item.size) : '');
            return '<tr data-invoice-line="' + index + '" data-sku="' + escapeHtml(item.sku_code) + '" data-tag-code="' + escapeHtml(item.tag_code) + '">' +
                '<td><strong class="d-block">' + label + '</strong><small class="text-muted">SKU: ' + escapeHtml(item.sku_code) + '</small></td>' +
                '<td class="text-center">' + Number(item.sold_qty) + '</td>' +
                '<td class="text-center"><input type="number" class="form-control form-control-sm text-center shop-return-qty" min="0" max="' + Number(item.sold_qty) + '" step="1" value="0" aria-label="Return quantity for ' + escapeHtml(item.sku_code) + '"></td>' +
                '<td><select class="form-select form-select-sm shop-return-condition" aria-label="Condition for ' + escapeHtml(item.sku_code) + '">' +
                    '<option value="Good (Restock)">Good (Restock)</option>' +
                    '<option value="Damaged (Do Not Restock)">Damaged (Do Not Restock)</option>' +
                '</select></td>' +
                '<td class="text-end font-monospace">' + formatMoney(item.paid_unit_price) + '</td>' +
                '<td class="text-end fw-bold font-monospace shop-return-line-credit">₹0.00</td>' +
            '</tr>';
        }).join('');
        document.getElementById('shop_return_selected_count').textContent = '0 of ' + invoiceItems.length + ' line item(s) · 0 pcs';
    }

    function renderReplacementItems() {
        if (!replacementItems.length) {
            replacementItemsBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Scan a replacement product from shop stock.</td></tr>';
            return;
        }
        replacementItemsBody.innerHTML = replacementItems.map(function(item, index) {
            return '<tr data-replacement-index="' + index + '">' +
                '<td><strong class="d-block">' + escapeHtml(item.name) + '</strong><small class="text-muted font-monospace">' + escapeHtml(item.sku_code) + '</small></td>' +
                '<td class="text-center">' + Number(item.available_qty) + '</td>' +
                '<td class="text-center"><input type="number" class="form-control form-control-sm text-center shop-replacement-qty" min="0" max="' + Number(item.available_qty) + '" step="1" value="' + Number(item.qty) + '" aria-label="Replacement quantity for ' + escapeHtml(item.sku_code) + '"></td>' +
                '<td class="text-end font-monospace">' + formatMoney(item.price) + '</td>' +
                '<td class="text-end fw-bold font-monospace">' + formatMoney(Number(item.price) * Number(item.qty)) + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-link text-danger p-0 shop-remove-replacement" aria-label="Remove ' + escapeHtml(item.sku_code) + '"><i class="bi bi-x-circle"></i></button></td>' +
            '</tr>';
        }).join('');
    }

    function setReturnType(type) {
        returnType = type;
        document.getElementById('shop_return_type').value = type;
        document.getElementById('shop_return_payment_section').classList.toggle('d-none', type !== 'RETURN');
        document.getElementById('shop_return_replacement_section').classList.toggle('d-none', type !== 'EXCHANGE');
        if (type === 'EXCHANGE') {
            document.getElementById('shop_return_settlement_mode').value = 'Exchange Adjustment';
        } else {
            const selectedPayment = document.querySelector('.shop-return-payment.active');
            document.getElementById('shop_return_settlement_mode').value = selectedPayment
                ? selectedPayment.getAttribute('data-settlement')
                : 'Cash';
        }
        document.querySelectorAll('.shop-return-action').forEach(function(button) {
            button.classList.toggle('active', button.getAttribute('data-return-type') === type);
        });
        updateReturnTotals();
    }

    function updateReturnTotals() {
        let returnAmount = 0;
        let selectedLineCount = 0;
        let selectedQty = 0;
        invoiceItemsBody.querySelectorAll('tr[data-invoice-line]').forEach(function(row) {
            const index = Number(row.getAttribute('data-invoice-line'));
            const item = invoiceItems[index];
            const qtyInput = row.querySelector('.shop-return-qty');
            const quantity = Math.min(Number(item.sold_qty), Math.max(0, Number(qtyInput.value) || 0));
            const lineCredit = quantity * Number(item.paid_unit_price);
            row.querySelector('.shop-return-line-credit').textContent = formatMoney(lineCredit);
            returnAmount += lineCredit;
            if (quantity > 0) {
                selectedLineCount += 1;
                selectedQty += quantity;
            }
        });

        let replacementAmount = 0;
        replacementItemsBody.querySelectorAll('tr[data-replacement-index]').forEach(function(row) {
            const index = Number(row.getAttribute('data-replacement-index'));
            const item = replacementItems[index];
            const qtyInput = row.querySelector('.shop-replacement-qty');
            item.qty = Math.min(Number(item.available_qty), Math.max(0, Number(qtyInput.value) || 0));
            const lineTotal = item.qty * Number(item.price);
            row.children[4].textContent = formatMoney(lineTotal);
            replacementAmount += lineTotal;
        });

        document.getElementById('shop_return_selected_count').textContent =
            selectedLineCount + ' of ' + invoiceItems.length + ' line item(s) · ' + selectedQty + ' pcs';
        document.getElementById('shop_return_paid_total').textContent = formatMoney(returnAmount);
        document.getElementById('shop_return_replacement_total').textContent = formatMoney(replacementAmount);
        const balance = document.getElementById('shop_return_balance');
        if (returnType === 'RETURN') {
            balance.textContent = 'Refund Customer: ' + formatMoney(returnAmount);
            balance.className = 'text-danger';
        } else {
            const difference = replacementAmount - returnAmount;
            if (difference > 0) {
                balance.textContent = 'Collect from Customer: ' + formatMoney(difference);
                balance.className = 'text-primary';
            } else if (difference < 0) {
                balance.textContent = 'Refund Customer: ' + formatMoney(Math.abs(difference));
                balance.className = 'text-danger';
            } else {
                balance.textContent = 'Even Swap (' + formatMoney(returnAmount) + ' − ' + formatMoney(replacementAmount) + ')';
                balance.className = 'text-success';
            }
        }
    }

    async function loadInvoice(invoiceId) {
        clearFormError();
        lookupStatus.innerHTML = '<span class="text-primary">Loading invoice items...</span>';
        try {
            const response = await fetch(invoiceDetailsUrl + '?invoice_id=' + encodeURIComponent(invoiceId), {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Could not load invoice.');
            activeInvoice = data.invoice;
            invoiceItems = data.items || [];
            replacementItems.length = 0;
            document.getElementById('shop_return_invoice_id').value = activeInvoice.id;
            document.getElementById('shop_return_customer_id').value = activeInvoice.customer_id;
            document.getElementById('shop_return_concern_id').value = activeInvoice.concern_id;
            document.getElementById('shop_return_summary_invoice').textContent = activeInvoice.invoice_no;
            document.getElementById('shop_return_summary_customer').textContent = activeInvoice.customer_name + ' · ' + activeInvoice.customer_phone;
            document.getElementById('shop_return_summary_date').textContent = activeInvoice.invoice_date;
            document.getElementById('shop_return_summary_total').textContent = formatMoney(activeInvoice.net_amount);
            document.getElementById('shop_return_reason').value = '';
            document.getElementById('shop_return_settlement_mode').value = 'Cash';
            document.querySelectorAll('.shop-return-payment').forEach(function(button) {
                button.classList.toggle('active', button.getAttribute('data-settlement') === 'Cash');
            });
            renderInvoiceItems();
            renderReplacementItems();
            setReturnType('RETURN');
            lookupStatus.innerHTML = '<span class="text-success">Invoice loaded. Select the items being returned.</span>';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalShopReturn')).show();
        } catch (error) {
            lookupStatus.innerHTML = '<span class="text-danger">' + escapeHtml(error.message) + '</span>';
        }
    }

    async function addReplacement() {
        if (!activeInvoice) return;
        const sku = replacementSkuInput.value.trim();
        if (!sku) return;
        clearFormError();
        const button = document.getElementById('shop_return_add_replacement');
        button.disabled = true;
        try {
            const url = replacementLookupUrl + '?invoice_id=' + encodeURIComponent(activeInvoice.id) + '&sku=' + encodeURIComponent(sku);
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Replacement product lookup failed.');
            const variant = data.variant;
            if (Number(variant.available_qty) <= 0) throw new Error('Replacement product has no available Main Store stock.');
            const existing = replacementItems.find(function(item) { return Number(item.id) === Number(variant.id); });
            if (existing) {
                if (Number(existing.qty) + 1 > Number(existing.available_qty)) throw new Error('Requested replacement quantity exceeds available stock.');
                existing.qty = Number(existing.qty) + 1;
            } else {
                replacementItems.push({
                    id: Number(variant.id),
                    sku_code: variant.sku_code,
                    name: variant.name,
                    size: variant.size,
                    price: Number(variant.price),
                    available_qty: Number(variant.available_qty),
                    qty: 1
                });
            }
            replacementSkuInput.value = '';
            renderReplacementItems();
            updateReturnTotals();
        } catch (error) {
            showFormError(error.message);
        } finally {
            button.disabled = false;
            replacementSkuInput.focus();
        }
    }

    lookupForm.addEventListener('submit', lookupCustomerInvoices);
    invoiceList.addEventListener('click', function(event) {
        const button = event.target.closest('[data-select-invoice]');
        if (button) loadInvoice(button.getAttribute('data-select-invoice'));
    });
    document.querySelectorAll('.shop-return-action').forEach(function(button) {
        button.addEventListener('click', function() {
            setReturnType(button.getAttribute('data-return-type'));
        });
    });
    document.querySelectorAll('.shop-return-payment').forEach(function(button) {
        button.addEventListener('click', function() {
            document.querySelectorAll('.shop-return-payment').forEach(function(option) {
                option.classList.toggle('active', option === button);
            });
            document.getElementById('shop_return_settlement_mode').value = button.getAttribute('data-settlement');
        });
    });
    invoiceItemsBody.addEventListener('input', updateReturnTotals);
    invoiceItemsBody.addEventListener('change', updateReturnTotals);
    document.getElementById('shop_return_scan_item').addEventListener('click', function() {
        const scanCode = document.getElementById('shop_return_item_search').value.trim().toUpperCase();
        if (!scanCode) return;
        const matchingIndexes = [];
        invoiceItems.forEach(function(item, index) {
            const codes = [item.sku_code, item.tag_code, item.variant_id];
            if (codes.some(function(code) { return String(code || '').trim().toUpperCase() === scanCode; })) {
                matchingIndexes.push(index);
            }
        });
        if (!matchingIndexes.length) {
            showFormError('This SKU or product tag is not on the selected invoice.');
            return;
        }
        const index = matchingIndexes.find(function(lineIndex) {
            const input = invoiceItemsBody.querySelectorAll('.shop-return-qty')[lineIndex];
            return Number(input.value) < Number(input.max);
        });
        if (index === undefined) {
            showFormError('The full sold quantity is already selected for this invoice item.');
            return;
        }
        clearFormError();
        invoiceItemsBody.querySelectorAll('.shop-return-scanned-row').forEach(function(row) {
            row.classList.remove('shop-return-scanned-row');
        });
        const scannedRow = invoiceItemsBody.querySelector('tr[data-invoice-line="' + index + '"]');
        scannedRow.classList.add('shop-return-scanned-row');
        scannedRow.scrollIntoView({behavior: 'smooth', block: 'center'});
        const input = invoiceItemsBody.querySelectorAll('.shop-return-qty')[index];
        input.value = Number(input.value) + 1;
        document.getElementById('shop_return_item_search').value = '';
        updateReturnTotals();
        document.getElementById('shop_return_item_search').focus();
    });
    document.getElementById('shop_return_item_search').addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('shop_return_scan_item').click();
        }
    });
    document.getElementById('shop_return_add_replacement').addEventListener('click', addReplacement);
    replacementSkuInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addReplacement();
        }
    });
    replacementItemsBody.addEventListener('input', updateReturnTotals);
    replacementItemsBody.addEventListener('click', function(event) {
        const button = event.target.closest('.shop-remove-replacement');
        if (!button) return;
        const row = button.closest('tr[data-replacement-index]');
        replacementItems.splice(Number(row.getAttribute('data-replacement-index')), 1);
        renderReplacementItems();
        updateReturnTotals();
    });
    returnForm.addEventListener('submit', function(event) {
        clearFormError();
        const inItems = [];
        invoiceItemsBody.querySelectorAll('tr[data-invoice-line]').forEach(function(row) {
            const index = Number(row.getAttribute('data-invoice-line'));
            const item = invoiceItems[index];
            const qty = Number(row.querySelector('.shop-return-qty').value) || 0;
            if (qty > 0) {
                inItems.push({
                    invoice_item_id: Number(item.invoice_item_id),
                    variant_id: Number(item.variant_id),
                    qty: qty,
                    condition_state: row.querySelector('.shop-return-condition').value
                });
            }
        });
        const outItems = replacementItems.filter(function(item) { return Number(item.qty) > 0; }).map(function(item) {
            return {variant_id: item.id, qty: Number(item.qty), unit_price: Number(item.price)};
        });
        if (!activeInvoice || inItems.length === 0) {
            event.preventDefault();
            showFormError('Select at least one invoice item and enter a return quantity.');
            return;
        }
        if (returnType === 'EXCHANGE' && outItems.length === 0) {
            event.preventDefault();
            showFormError('Scan at least one replacement product for this exchange.');
            return;
        }
        document.getElementById('shop_return_in_items').value = JSON.stringify(inItems);
        document.getElementById('shop_return_out_items').value = JSON.stringify(outItems);
        document.getElementById('shop_return_process_button').disabled = true;
    });
});

function openViewReturnModal(r) {
    if (!r) return;
    document.getElementById('view_return_no').innerText = r.return_no || '';
    document.getElementById('view_return_customer').innerText = r.customer_name || '-';
    document.getElementById('view_return_variant').innerText = r.returned_variant_name || '-';
    document.getElementById('view_return_sku').innerText = r.returned_sku || '-';
    document.getElementById('view_return_qty').innerText = '+' + parseFloat(r.returned_qty || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('view_return_date').innerText = r.return_date ? r.return_date.substring(0, 10) : '-';
    document.getElementById('view_return_reason').innerText = r.reason || 'None specified';

    const typeBadge = document.getElementById('view_return_type_badge');
    const excRow = document.getElementById('view_exchange_row');
    if (r.type === 'exchange') {
        typeBadge.className = 'badge bg-info text-dark';
        typeBadge.innerText = 'EXCHANGE';
        if (excRow) {
            excRow.style.display = 'flex';
            document.getElementById('view_exchange_info').innerText = (r.exchange_variant_name || '') + ' (-' + (r.exchange_qty || 0) + ')';
        }
    } else {
        typeBadge.className = 'badge bg-secondary';
        typeBadge.innerText = 'RETURN';
        if (excRow) excRow.style.display = 'none';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalViewReturn'));
    modal.show();
}

function openEditReturnModal(r) {
    if (!r) return;
    document.getElementById('edit_return_id').value = r.id || '';
    document.getElementById('edit_return_no').value = r.return_no || '';
    document.getElementById('edit_return_date').value = r.return_date ? r.return_date.substring(0, 10) : '';
    document.getElementById('edit_return_reason').value = r.reason || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditReturn'));
    modal.show();
}
</script>

<style>
.shop-return-lookup {
    max-width: 520px;
}
.shop-return-action,
.shop-return-payment {
    border: 1px solid #dbe2ea;
    background: #fff;
    color: #334155;
    transition: border-color .15s ease, background-color .15s ease, color .15s ease;
}
.shop-return-action.active {
    border-color: #16a34a;
    background: #f0fdf4;
    color: #166534;
}
.shop-return-payment.active {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #1d4ed8;
    box-shadow: 0 0 0 1px #3b82f6 inset;
}
.shop-return-scanned-row > td {
    background-color: #fff3cd !important;
}
.shop-return-scanned-row > td:first-child {
    box-shadow: inset 4px 0 #f0ad4e;
}
.shop-return-scan {
    width: min(100%, 390px);
}
@media (max-width: 575.98px) {
    .shop-return-scan {
        width: 100%;
    }
}
</style>
