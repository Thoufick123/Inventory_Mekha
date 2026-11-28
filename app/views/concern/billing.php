<?php
$posPaymentMethodLabels = [
    'cash' => 'CASH',
    'upi' => 'UPI / QR',
    'card' => 'CARD / POS',
    'split' => 'CASH + UPI',
    'bank_transfer' => 'BANK TRANSFER',
    'credit' => 'CREDIT',
];
$posPaymentMethodLabel = static function ($method) use ($posPaymentMethodLabels): string {
    $key = strtolower(trim((string)$method));
    return $posPaymentMethodLabels[$key] ?? strtoupper(str_replace('_', ' ', $key));
};
?>
<div class="container-fluid px-0">
    <!-- Top Breadcrumb & Page Header (Exact Replica of Image 1) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <div class="text-secondary small mb-1">
                <a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a>
                <span class="mx-1">/</span>
                <span class="text-muted">Shop</span>
                <span class="mx-1">/</span>
                <span class="text-dark fw-semibold">POS Terminal</span>
            </div>
            <div class="d-flex align-items-center gap-3 mt-1">
                <a href="<?= \App\Core\Controller::url('/m/billing') ?>" class="btn btn-light rounded-circle shadow-sm border p-0 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="Back to POS Bills">
                    <i class="bi bi-arrow-left fs-6 text-dark"></i>
                </a>
                <div style="width: 44px; height: 44px; background: #e0f2fe; color: #0284c7; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.45rem; flex-shrink: 0;">
                    <i class="bi bi-qr-code"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.01em;"><?= !empty($editData) ? 'Edit POS Invoice ' . htmlspecialchars($editData['invoice_no'], ENT_QUOTES, 'UTF-8') : 'POS Billing Terminal' ?></h4>
                    <small class="text-muted">
                        <i class="bi bi-shop me-1 text-primary"></i> <?= htmlspecialchars($concernName) ?> &bull; 
                        <span class="text-dark">Staff:</span> <?= htmlspecialchars($staffName) ?>
                    </small>
                </div>
            </div>

        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.82rem;">
                <span style="color: #10b981; font-size: 0.75rem;">●</span> QR Scanner Active
            </span>
            <button class="btn btn-outline-secondary btn-sm shadow-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalInvoiceHistory">
                <i class="bi bi-receipt"></i> Invoices History (<?= count($invoices) ?>)
            </button>
        </div>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export POS Sales Invoices (Excel & PDF)';
    $exportSubtitle = 'Download POS store transactions, register sales receipts, tax breakdowns & cashier collections';
    $exportUrl = \App\Core\Controller::url('/m/billing/export');
    $prefix = 'pos_bill_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Main Terminal Grid (Left: Customer & Coupons, Right: Scanner & Sale Details) -->
    <form id="pos_billing_form" action="<?= \App\Core\Controller::url(!empty($editData) ? '/m/billing/update' : '/m/billing/complete') ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="cart_items_json" id="cart_items_json" value="[]">
        <input type="hidden" name="payment_method" id="input_payment_method" value="<?= htmlspecialchars($editData['payment_method'] ?? 'cash', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="invoice_id" id="editing_invoice_id" value="<?= (int)($editData['id'] ?? 0) ?>">

        <?php if (!empty($editData)): ?>
        <div class="alert alert-warning border-warning-subtle mb-3">
            <strong>Editing <?= htmlspecialchars($editData['invoice_no'], ENT_QUOTES, 'UTF-8') ?>.</strong>
            Current catalog prices and GST rates will be used. Previously saved item discounts and coupons are not carried forward automatically; review the new total before saving.
        </div>
        <?php endif; ?>

        <div class="row g-3">
            <!-- ============================================== -->
            <!-- LEFT COLUMN: CUSTOMER DETAILS & COUPONS -->
            <!-- ============================================== -->
            <div class="col-lg-5">
                <!-- Customer & Billed By Card (Image 1) -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3" style="border: 1px solid #e2e8f0 !important;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-vcard text-primary fs-5"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Customer & Billed By</h6>
                                <span class="text-muted" style="font-size: 0.76rem;">Mandatory fields marked with *</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #fee2e2; color: #ef4444; font-size: 0.74rem;">
                                * Name, Phone, Address
                            </span>
                            <span id="customer_history_badge" class="badge rounded-pill px-2 py-1 fw-bold bg-success-subtle text-success border border-success-subtle d-none" style="font-size: 0.74rem;">
                                <i class="bi bi-check-circle me-1"></i><span id="customer_history_badge_text">Saved</span>
                            </span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3 align-items-end">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-bold mb-1">BILLING FOR</label>
                            <div class="d-flex gap-2" id="billing_party_buttons" role="group" aria-label="Billing party type">
                                <button type="button" class="btn <?= !empty($editData) && $editData['billing_party_type'] === 'company' ? 'btn-outline-primary' : 'btn-primary active' ?> flex-fill" data-billing-party="customer">
                                    <i class="bi bi-person me-1"></i> Customer
                                </button>
                                <button type="button" class="btn <?= !empty($editData) && $editData['billing_party_type'] === 'company' ? 'btn-primary active' : 'btn-outline-primary' ?> flex-fill" data-billing-party="company">
                                    <i class="bi bi-buildings me-1"></i> Company / Bulk
                                </button>
                            </div>
                            <select name="billing_party_type" id="billing_party_type" class="d-none" required aria-hidden="true">
                                <option value="customer" <?= empty($editData) || $editData['billing_party_type'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                <option value="company" <?= !empty($editData) && $editData['billing_party_type'] === 'company' ? 'selected' : '' ?>>Company / Bulk</option>
                            </select>
                        </div>
                        <div class="col-12" id="customer_channel_wrap">
                            <label for="pos_customer_channel" class="form-label text-secondary small fw-bold mb-1">CUSTOMER CHANNEL / SOURCE *</label>
                            <div class="input-group flex-nowrap">
                                <span class="input-group-text bg-light text-primary"><i class="bi bi-diagram-3"></i></span>
                                <select name="customer_channel" id="pos_customer_channel" class="d-none">
                                    <?php
                                        $channelsList = !empty($customerChannels) ? $customerChannels : [
                                            ['id' => 0, 'channel_key' => 'direct', 'channel_name' => 'Direct Customer', 'is_order_channel' => 0, 'is_default' => 1],
                                            ['id' => 0, 'channel_key' => 'website', 'channel_name' => 'Website Customer', 'is_order_channel' => 1, 'is_default' => 0],
                                            ['id' => 0, 'channel_key' => 'whatsapp', 'channel_name' => 'WhatsApp Customer', 'is_order_channel' => 1, 'is_default' => 0],
                                        ];
                                        $initialChannelName = $channelsList[0]['channel_name'] ?? 'Direct Customer';
                                        $hasDirectDefault = false;
                                        foreach ($channelsList as $chCheck) {
                                            if ($chCheck['channel_key'] === 'direct') {
                                                $hasDirectDefault = true;
                                                $initialChannelName = $chCheck['channel_name'];
                                                break;
                                            }
                                        }
                                        foreach ($channelsList as $idxCh => $ch):
                                            $isInitSel = $hasDirectDefault ? ($ch['channel_key'] === 'direct') : ($idxCh === 0);
                                    ?>
                                        <option value="<?= htmlspecialchars($ch['channel_key']) ?>"
                                                data-channel-id="<?= (int)($ch['id'] ?? 0) ?>"
                                                data-order-channel="<?= !empty($ch['is_order_channel']) ? '1' : '0' ?>"
                                                <?= !empty($editData)
                                                    ? ($ch['channel_key'] === $editData['customer_channel'] ? 'selected' : '')
                                                    : ($isInitSel ? 'selected' : '') ?>>
                                            <?= htmlspecialchars($ch['channel_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="dropdown flex-grow-1" style="min-width: 0;">
                                    <button type="button"
                                            class="form-select text-start fw-semibold rounded-0 text-truncate w-100"
                                            id="customerChannelDropdownBtn"
                                            data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside"
                                            aria-expanded="false"
                                            style="height: 100%; box-shadow: none;">
                                        <?= htmlspecialchars($initialChannelName) ?>
                                    </button>
                                    <div class="dropdown-menu w-100 shadow-lg p-2 border" id="customerChannelDropdownMenu" style="max-height: 290px; overflow-y: auto; z-index: 1065; border-radius: 10px;"></div>
                                </div>
                                <?php if (\App\Core\Auth::can('billing', 'add') || \App\Core\Auth::can('billing', 'edit') || \App\Core\Auth::can('billing', 'delete')): ?>
                                <button type="button" class="btn btn-outline-primary fw-semibold text-nowrap" id="btn_add_customer_channel" data-bs-toggle="modal" data-bs-target="#modalAddCustomerChannel" title="Manage Customer Channels (Add, View, Edit, Delete)">
                                    <i class="bi bi-plus-lg"></i> New
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3 d-none" id="company_details_wrap">
                        <div class="col-12 col-sm-6">
                            <label for="company_name" class="form-label text-secondary small fw-bold mb-1">COMPANY NAME *</label>
                            <input type="text" name="company_name" id="company_name" class="form-control" maxlength="150" required disabled>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="company_address" class="form-label text-secondary small fw-bold mb-1">ADDRESS</label>
                            <input type="text" name="company_address" id="company_address" class="form-control" maxlength="255" disabled>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="company_phone" class="form-label text-secondary small fw-bold mb-1">PHONE</label>
                            <input type="tel" name="company_phone" id="company_phone" class="form-control" maxlength="30" disabled>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="company_email" class="form-label text-secondary small fw-bold mb-1">EMAIL</label>
                            <input type="email" name="company_email" id="company_email" class="form-control" maxlength="100" disabled>
                        </div>
                        <div class="col-12">
                            <label for="company_gstin" class="form-label text-secondary small fw-bold mb-1">GSTIN</label>
                            <input type="text" name="company_gstin" id="company_gstin" class="form-control text-uppercase" maxlength="15" disabled>
                        </div>
                    </div>

                    <!-- Customer details and invoice GST controls -->
                    <div id="customer_contact_fields">
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-sm-6 position-relative">
                            <label class="form-label text-secondary small fw-bold mb-1" style="font-size: 0.78rem;">
                                <i class="bi bi-phone text-muted me-1"></i> MOBILE NUMBER *
                            </label>
                            <input type="tel" name="customer_mobile" id="cust_mobile" class="form-control" placeholder="10-digit mobile number *" maxlength="10" required autocomplete="off" style="border-radius: 8px; font-size: 0.88rem;">
                            <!-- Customer Previous Orders & Autocomplete Dropdown -->
                            <div id="cust_lookup_dropdown" class="position-absolute bg-white border rounded-3 shadow-lg p-1 d-none" style="top: 100%; left: 0; right: 0; z-index: 1060; max-height: 220px; overflow-y: auto; margin-top: 4px;"></div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label text-secondary small fw-bold mb-1" style="font-size: 0.78rem;">
                                <i class="bi bi-person text-muted me-1"></i> CUSTOMER NAME *
                            </label>
                            <input type="text" name="customer_name" id="cust_name" class="form-control" placeholder="Customer full name *" required style="border-radius: 8px; font-size: 0.88rem;">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-bold mb-1" style="font-size: 0.78rem;">
                                <i class="bi bi-geo-alt text-muted me-1"></i> CUSTOMER ADDRESS *
                            </label>
                            <input type="text" name="customer_address" id="cust_address" class="form-control" placeholder="Billing address / city *" required style="border-radius: 8px; font-size: 0.88rem;">
                        </div>
                    </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-12">
                            <label for="billed_by_staff" class="form-label text-secondary small fw-bold mb-1" style="font-size: 0.78rem;">
                                <i class="bi bi-person-badge text-muted me-1"></i> BILLED BY STAFF
                            </label>
                            <input type="text" id="billed_by_staff" class="form-control bg-light text-muted" value="<?= htmlspecialchars($staffName) ?>" readonly style="border-radius: 8px; font-size: 0.88rem;">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label text-secondary small fw-bold mb-1" style="font-size: 0.78rem;">
                                <i class="bi bi-receipt text-muted me-1"></i> GST TYPE
                            </label>
                            <select name="gst_type" id="gst_type" class="form-select" style="border-radius: 8px; font-size: 0.88rem;">
                                <option value="both" <?= empty($editData) || $editData['gst_type'] === 'both' ? 'selected' : '' ?>>Both CGST & SGST</option>
                                <option value="igst" <?= !empty($editData) && $editData['gst_type'] === 'igst' ? 'selected' : '' ?>>IGST (Inter-state)</option>
                                <option value="exempt" <?= !empty($editData) && $editData['gst_type'] === 'exempt' ? 'selected' : '' ?>>None / Exempt (0%)</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label text-secondary small fw-bold mb-1" style="font-size: 0.78rem;">
                                <i class="bi bi-percent text-muted me-1"></i> GST MODE
                            </label>
                            <select name="gst_price_mode" id="gst_price_mode" class="form-select" style="border-radius: 8px; font-size: 0.88rem;">
                                <option value="exclude" <?= empty($editData) || $editData['gst_price_mode'] === 'exclude' ? 'selected' : '' ?>>Exclude GST (Add GST)</option>
                                <option value="include" <?= !empty($editData) && $editData['gst_price_mode'] === 'include' ? 'selected' : '' ?>>Include GST (Extract GST)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Coupons & Offers Panel -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3" style="border: 1px solid #e2e8f0 !important;" id="pos_coupons_panel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <span class="fw-bold text-dark small"><i class="bi bi-ticket-perforated text-primary me-1"></i> COUPONS &amp; OFFERS</span>
                        <a href="<?= \App\Core\Controller::url('/m/coupons') ?>" target="_blank" class="small text-decoration-none fw-semibold">Manage Coupons <i class="bi bi-box-arrow-up-right"></i></a>
                    </div>
                    <input type="hidden" name="applied_coupon_id" id="applied_coupon_id" value="">
                    <input type="hidden" name="applied_coupon_mode" id="applied_coupon_mode" value="DIRECT">
                    <div class="row g-2 align-items-end">
                        <div class="col-12">
                            <label for="pos_coupon_select" class="form-label small text-muted mb-1 fw-semibold">Select Active Coupon</label>
                            <select id="pos_coupon_select" class="form-select form-select-sm">
                                <option value="">-- No Coupon Applied --</option>
                                <?php foreach (($activeCoupons ?? []) as $cp):
                                    $offerText = $cp['discount_type'] === 'PERCENT'
                                        ? number_format((float)$cp['discount_value'], 0) . '% OFF'
                                        : '₹' . number_format((float)$cp['discount_value'], 2) . ' OFF';
                                ?>
                                    <option value="<?= (int)$cp['id'] ?>"
                                            data-code="<?= htmlspecialchars($cp['code']) ?>"
                                            data-type="<?= htmlspecialchars($cp['discount_type']) ?>"
                                            data-value="<?= (float)$cp['discount_value'] ?>"
                                            data-min="<?= (float)$cp['min_redemption_amount'] ?>">
                                        <?= htmlspecialchars($cp['code']) ?> — <?= htmlspecialchars($cp['title']) ?> (<?= $offerText ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1 fw-semibold">Coupon Mode</label>
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <input type="radio" class="btn-check" name="coupon_mode_radio" id="coupon_mode_direct" value="DIRECT" checked>
                                <label class="btn btn-outline-primary" for="coupon_mode_direct">Direct Bill Discount</label>
                                <input type="radio" class="btn-check" name="coupon_mode_radio" id="coupon_mode_product" value="PRODUCT_BUY">
                                <label class="btn btn-outline-primary" for="coupon_mode_product">Buy Product w/ Coupon</label>
                            </div>
                        </div>
                    </div>
                    <div id="pos_coupon_feedback" class="small mt-2 d-none"></div>
                </div>

                <!-- Payment Method Section -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3" style="border: 1px solid #e2e8f0 !important;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold text-dark small"><i class="bi bi-credit-card text-primary me-1"></i> PAYMENT METHOD</span>
                        <span class="text-muted small" style="font-size: 0.75rem;">SELECT CUSTOMER PAYMENT MODE</span>
                    </div>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-12">
                            <label for="pos_invoice_date" class="form-label text-secondary small fw-bold mb-1">BILL DATE *</label>
                            <input type="date" name="invoice_date" id="pos_invoice_date" class="form-control" value="<?= htmlspecialchars($editData['invoice_date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-12 d-none" id="direct_bill_wrap">
                            <div class="form-check border rounded-3 bg-light px-3 py-2">
                                <input type="checkbox" name="is_direct_bill" id="is_direct_bill" value="1" class="form-check-input" <?= !empty($editData['is_direct_bill']) ? 'checked' : '' ?>>
                                <label for="is_direct_bill" class="form-check-label fw-semibold small">Separate as Direct Bill</label>
                                <small class="d-block text-muted ms-4">Direct bills appear in their own tab and are excluded from standard POS totals.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row row-cols-4 g-2 mb-3">
                        <div class="col">
                            <button type="button" class="pay-method-btn <?= empty($editData) || $editData['payment_method'] === 'cash' ? 'active' : '' ?> text-center p-2 rounded-3 border w-100" data-method="cash" aria-pressed="<?= empty($editData) || $editData['payment_method'] === 'cash' ? 'true' : 'false' ?>">
                                <i class="bi bi-cash-stack fs-4 d-block mb-1"></i>
                                <span class="fw-bold small d-block">Cash</span>
                            </button>
                        </div>
                        <div class="col">
                            <button type="button" class="pay-method-btn <?= !empty($editData) && $editData['payment_method'] === 'upi' ? 'active' : '' ?> text-center p-2 rounded-3 border w-100" data-method="upi" aria-pressed="<?= !empty($editData) && $editData['payment_method'] === 'upi' ? 'true' : 'false' ?>">
                                <i class="bi bi-qr-code fs-4 d-block mb-1"></i>
                                <span class="fw-bold small d-block">UPI / QR</span>
                            </button>
                        </div>
                        <div class="col">
                            <button type="button" class="pay-method-btn <?= !empty($editData) && $editData['payment_method'] === 'card' ? 'active' : '' ?> text-center p-2 rounded-3 border w-100" data-method="card" aria-pressed="<?= !empty($editData) && $editData['payment_method'] === 'card' ? 'true' : 'false' ?>">
                                <i class="bi bi-credit-card fs-4 d-block mb-1"></i>
                                <span class="fw-bold small d-block">Card / POS</span>
                            </button>
                        </div>
                        <div class="col">
                            <button type="button" class="pay-method-btn <?= !empty($editData) && $editData['payment_method'] === 'split' ? 'active' : '' ?> text-center p-2 rounded-3 border w-100" data-method="split" aria-pressed="<?= !empty($editData) && $editData['payment_method'] === 'split' ? 'true' : 'false' ?>">
                                <i class="bi bi-pie-chart fs-4 d-block mb-1"></i>
                                <span class="fw-bold small d-block">Cash + UPI</span>
                            </button>
                        </div>
                    </div>

                    <div id="split_payment_section" class="split-payment-panel rounded-3 p-3 mb-3" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-dark"><i class="bi bi-wallet2 me-1"></i> Cash + UPI Split Breakdown</span>
                            <button type="button" id="split_auto_balance" class="btn btn-sm btn-outline-success rounded-pill px-2">
                                <i class="bi bi-magic me-1"></i>Auto-Balance
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label for="split_cash_amount" class="form-label small text-muted mb-1"><i class="bi bi-cash me-1"></i>Cash Amount (₹)</label>
                                <input type="number" id="split_cash_amount" class="form-control text-end fw-bold font-monospace" min="0" step="0.01" value="0.00">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="split_upi_amount" class="form-label small text-muted mb-1"><i class="bi bi-qr-code me-1"></i>UPI Amount (₹)</label>
                                <input type="number" id="split_upi_amount" class="form-control text-end fw-bold font-monospace" min="0" step="0.01" value="0.00">
                            </div>
                            <div class="col-12">
                                <label for="split_upi_reference" class="form-label small text-muted mb-1">UPI Ref / Transaction ID / App</label>
                                <input type="text" id="split_upi_reference" class="form-control" placeholder="e.g. GPay / PhonePe / Ref #402918237">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 small">
                            <span class="text-muted">Split Total: <strong id="split_total_amount" class="text-dark font-monospace">₹0.00</strong></span>
                            <span id="split_balance_status" class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">Balanced (₹0.00)</span>
                        </div>
                    </div>

                    <!-- Cash sales can be manually classified as Online. -->
                    <div id="online_classification_wrap" class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" id="toggle_online" name="is_online" value="1" <?= !empty($editData['is_online']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold text-dark small" for="toggle_online" style="font-size: 0.82rem;">
                            Show this cash sale in the Online section
                        </label>
                        <div class="text-muted" style="font-size: 0.74rem;">
                            Cash sales go to Cash by default. UPI, Card, and Cash + UPI sales go to Online.
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: SCANNER, CART & SALE DETAILS -->
            <div class="col-lg-7">
                <!-- Scan Product QR Code Card -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3" id="pos_scanner_card" style="border: 1px solid #e2e8f0 !important;">
                    <!-- Header with Title & Scanner Options Buttons -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-upc-scan text-primary fs-5"></i>
                            <h6 class="fw-bold text-dark mb-0">Scan Product QR Code</h6>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 d-flex align-items-center gap-1 shadow-sm text-secondary" id="btnOpenScannerOptions" style="font-size: 0.76rem;" title="Configure scanner options & audio">
                                <i class="bi bi-sliders2"></i> Scanner Options
                            </button>
                        </div>
                    </div>

                    <!-- Large Input Box with Blue Add Button -->
                    <div class="d-flex align-items-stretch mb-2" style="border: 1.5px solid #3b82f6; border-radius: 12px; overflow: hidden; background: #ffffff;">
                        <div style="background: #2563eb; color: #ffffff; width: 48px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;" id="qr_icon_box" title="Barcode / QR input">
                            <i class="bi bi-qr-code" id="active_input_icon"></i>
                        </div>
                        <input type="text" id="pos_qr_input" list="pos_catalog_list" class="form-control border-0 shadow-none px-3 text-dark" placeholder="<?= \App\Core\Auth::can('billing', 'create') ? 'Point QR scanner or type Product Code / name' : 'View only access - billing terminal disabled' ?>" style="font-size: 0.95rem; border-radius: 0;" autocomplete="off" <?= \App\Core\Auth::can('billing', 'create') ? 'autofocus' : 'disabled' ?>>
                        <datalist id="pos_catalog_list">
                            <?php foreach ($variants as $v): ?>
                                <option value="<?= htmlspecialchars($v['product_code']) ?>"><?= htmlspecialchars($v['name']) ?> (₹<?= number_format((float)$v['price'], 0) ?><?= !empty($v['is_complementary']) ? ' · Complimentary' : '' ?>)</option>
                            <?php endforeach; ?>
                        </datalist>
                        <button type="button" id="btn_add_qr" class="btn text-white px-3 fw-bold d-flex align-items-center gap-1" style="background: #4f46e5; border-radius: 0; border: none; font-size: 0.92rem; white-space: nowrap;" <?= !\App\Core\Auth::can('billing', 'create') ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-right-circle"></i> Add
                        </button>
                    </div>
                    <div id="pos_inline_error" class="alert alert-danger d-none py-2 px-3 mb-2 small" role="alert" aria-live="polite"></div>

                    <!-- Scanner Status & Interactive Options Toolbar -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-1.5 px-2.5 mb-2 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.75rem;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill bg-white text-dark border px-2 py-1 d-flex align-items-center gap-1 shadow-sm" id="badge_scanner_mode" style="cursor: pointer;" title="Active scan input mode - Click to change">
                                <i class="bi bi-upc text-primary" id="icon_active_mode"></i> <span id="label_active_mode">Barcode Gun</span>
                            </span>
                            <button type="button" class="btn btn-sm p-0 text-decoration-none d-flex align-items-center gap-1" id="btn_quick_toggle_beep" title="Click to mute/unmute scan beep">
                                <i class="bi bi-volume-up-fill text-success" id="icon_quick_beep"></i> <span class="text-secondary fw-semibold" id="label_quick_beep">Beep ON</span>
                            </button>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <button type="button" class="btn btn-sm p-0 text-decoration-none d-flex align-items-center gap-1" id="btn_quick_toggle_qty" title="Click to toggle quantity mode">
                                <i class="bi bi-123 text-primary"></i> <span class="badge bg-light text-dark border" id="badge_quick_qty">+1 / Scan</span>
                            </button>
                        </div>
                    </div>

                    <!-- Subtext below scanner input -->
                    <div class="d-flex justify-content-between align-items-center small px-1 mb-2">
                        <span class="text-muted" style="font-size: 0.76rem;">
                            <i class="bi bi-info-circle me-1"></i> Each scanned piece is added one by one.
                        </span>
                        <span class="text-primary fw-semibold" id="scanner_status_text" style="font-size: 0.76rem;">
                            Ready for next scan...
                        </span>
                    </div>
                </div>

                <!-- Billing Cart Table (Image 1 & 2) -->
                <style>
                    #cart_table {
                        width: 100%;
                        table-layout: fixed;
                        font-size: 0.78rem;
                    }

                    #cart_table th,
                    #cart_table td {
                        padding: 0.3rem 0.2rem;
                        overflow-wrap: anywhere;
                    }

                    #cart_table th:nth-child(1), #cart_table td:nth-child(1) { width: 4%; }
                    #cart_table th:nth-child(2), #cart_table td:nth-child(2) { width: 25%; }
                    #cart_table th:nth-child(3), #cart_table td:nth-child(3) { width: 9%; }
                    #cart_table th:nth-child(4), #cart_table td:nth-child(4) { width: 8%; }
                    #cart_table th:nth-child(5), #cart_table td:nth-child(5) { width: 11%; }
                    #cart_table th:nth-child(6), #cart_table td:nth-child(6) { width: 7%; }
                    #cart_table th:nth-child(7), #cart_table td:nth-child(7) { width: 10%; }
                    #cart_table th:nth-child(8), #cart_table td:nth-child(8) { width: 22%; }
                    #cart_table th:nth-child(9), #cart_table td:nth-child(9) { width: 4%; }

                    @media (max-width: 1199.98px) {
                        #cart_table th:nth-child(3), #cart_table td:nth-child(3),
                        #cart_table th:nth-child(5), #cart_table td:nth-child(5) {
                            display: none;
                        }

                        #cart_table th:nth-child(1), #cart_table td:nth-child(1) { width: 4%; }
                        #cart_table th:nth-child(2), #cart_table td:nth-child(2) { width: 39%; }
                        #cart_table th:nth-child(4), #cart_table td:nth-child(4) { width: 9%; }
                        #cart_table th:nth-child(6), #cart_table td:nth-child(6) { width: 8%; }
                        #cart_table th:nth-child(7), #cart_table td:nth-child(7) { width: 12%; }
                        #cart_table th:nth-child(8), #cart_table td:nth-child(8) { width: 23%; }
                        #cart_table th:nth-child(9), #cart_table td:nth-child(9) { width: 5%; }
                    }

                    @media (max-width: 575.98px) {
                        #cart_table th:nth-child(6), #cart_table td:nth-child(6) {
                            display: none;
                        }

                        #cart_table th:nth-child(2), #cart_table td:nth-child(2) { width: 42%; }
                        #cart_table th:nth-child(4), #cart_table td:nth-child(4) { width: 10%; }
                        #cart_table th:nth-child(7), #cart_table td:nth-child(7) { width: 14%; }
                        #cart_table th:nth-child(8), #cart_table td:nth-child(8) { width: 25%; }
                    }
                </style>
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3" style="border: 1px solid #e2e8f0 !important;">
                    <table class="table table-hover table-sm align-middle mb-0" id="cart_table">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 0.75rem; text-transform: uppercase;">
                                <th>#</th>
                                <th>ITEM DETAILS</th>
                                <th>HSN CODE</th>
                                <th>QTY</th>
                                <th>BASE PRICE</th>
                                <th>GST %</th>
                                <th>DISCOUNT</th>
                                <th class="text-end">BILLED PRICE</th>
                                <th class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="cart_table_body">
                            <!-- Populated dynamically or Empty State -->
                        </tbody>
                    </table>

                    <!-- Cart Empty State (Exact Image 1 Replica) -->
                    <div id="cart_empty_state" class="py-5 text-center">
                        <div style="width: 64px; height: 64px; background: #eff6ff; color: #4f46e5; border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 0.75rem;">
                            <i class="bi bi-bag-plus"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Billing Cart is Empty</h5>
                        <p class="text-muted small mb-0">Scan a garment QR code or enter its SKU above to start billing.</p>
                    </div>

                    <!-- Cart Footer Summary Row (Image 2 Top) -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
                        <span class="badge rounded-pill px-3 py-2 fw-semibold" id="badge_items_count" style="background: #e0f2fe; color: #0284c7; font-size: 0.8rem;">
                            <i class="bi bi-boxes me-1"></i> <span id="display_items_count">0</span> Items Scanned
                        </span>
                        <div class="text-end">
                            <span class="text-secondary small fw-bold me-2" style="font-size: 0.78rem;">TAXABLE BASE SUBTOTAL</span>
                            <span class="fw-bold text-dark font-monospace fs-6" id="display_taxable_subtotal">₹0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Two Summary Cards (Image 2) -->
                <div class="row g-3 mb-3">
                    <!-- GST Inclusive Card -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100" style="border: 1px solid #e2e8f0 !important;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark small"><i class="bi bi-receipt text-primary me-1"></i> GST Amount</span>
                                <span class="badge rounded-pill px-2 py-1 fw-bold" id="badge_gst_incl" style="background: #dcfce7; color: #15803d; font-size: 0.74rem;">Added ₹0.00</span>
                            </div>
                            <div class="small d-flex justify-content-between text-muted mb-1 font-monospace">
                                <span>CGST: <strong id="display_cgst" class="text-dark">₹0.00</strong></span>
                                <span>SGST: <strong id="display_sgst" class="text-dark">₹0.00</strong></span>
                            </div>
                            <small class="text-muted" style="font-size: 0.74rem;">
                                <i class="bi bi-info-circle me-1"></i> Auto-calculated from product GST %
                            </small>
                        </div>
                    </div>

                    <!-- Total Discount Given Card -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100" style="border: 1px solid #e2e8f0 !important;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark small"><i class="bi bi-tag text-danger me-1"></i> Total Discount Given</span>
                                <span class="badge rounded-pill px-2 py-1 fw-bold" id="badge_discount" style="background: #fee2e2; color: #dc2626; font-size: 0.74rem;">-₹0.00</span>
                            </div>
                            <div class="small text-muted mb-1">
                                Total Customer Savings: <strong id="display_savings" class="text-dark font-monospace">₹0.00</strong>
                            </div>
                            <small class="text-success fw-semibold" style="font-size: 0.74rem;">
                                <i class="bi bi-check-lg me-1"></i> Discount compared against MRP
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Net Payable Amount Dark Banner (Image 2 Middle) -->
                <div class="p-3 mb-3 d-flex justify-content-between align-items-center shadow-sm" style="background: #0f172a; border-radius: 14px; color: #ffffff;">
                    <div>
                        <div class="text-uppercase small fw-bold tracking-wider text-light mb-1" style="letter-spacing: 0.05em; font-size: 0.8rem;">
                            NET PAYABLE AMOUNT
                        </div>
                        <div class="text-secondary small d-flex align-items-center gap-1" style="font-size: 0.76rem;">
                            <i class="bi bi-shield-check text-info"></i> Includes GST, item discounts & shipping
                        </div>
                    </div>
                    <div class="fw-bold font-monospace" id="display_net_payable" style="font-size: 2.2rem; color: #38bdf8;">
                        ₹0.00
                    </div>
                </div>

                <div id="order_channel_payment_fields" style="display: none;">
                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-4">
                        <div class="payment-detail-card h-100">
                            <label for="pos_shipping_amount" class="form-label small fw-bold text-uppercase text-muted"><i class="bi bi-truck me-1"></i>Shipping (₹)</label>
                            <input type="number" id="pos_shipping_amount" name="shipping_amount" class="form-control text-end fw-bold font-monospace" min="0" max="9999999999.99" step="0.01" value="<?= number_format((float)($editData['shipping_amount'] ?? 0), 2, '.', '') ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="payment-detail-card h-100">
                            <label for="pos_payment_status" class="form-label small fw-bold text-uppercase text-muted"><i class="bi bi-check-circle me-1"></i>Payment Status</label>
                            <select id="pos_payment_status" name="payment_status" class="form-select fw-semibold">
                                <option value="paid" <?= empty($editData) || $editData['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="partial" <?= !empty($editData) && $editData['payment_status'] === 'partial' ? 'selected' : '' ?>>Partially Paid / COD</option>
                                <option value="unpaid" <?= !empty($editData) && $editData['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid / COD</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="payment-detail-card h-100">
                            <label for="pos_payment_screenshot" class="form-label small fw-bold text-uppercase text-muted"><i class="bi bi-image me-1"></i>Payment Screenshot</label>
                            <input type="file" id="pos_payment_screenshot" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>

                <div id="advance_payment_section" class="row g-2 mb-3" style="display: none;">
                    <div class="col-12 col-md-6">
                        <div class="payment-detail-card h-100">
                            <label for="advance_paid_amount" class="form-label small fw-bold text-uppercase text-muted"><i class="bi bi-cash-coin me-1"></i>Advance Paid (₹)</label>
                            <input type="number" id="advance_paid_amount" name="advance_paid" class="form-control text-end fw-bold font-monospace" min="0" max="9999999999.99" step="0.01" value="<?= number_format((float)($editData['paid_amount'] ?? 0), 2, '.', '') ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="payment-detail-card h-100">
                            <label for="pending_cod_amount" class="form-label small fw-bold text-uppercase text-muted"><i class="bi bi-hourglass-split me-1"></i>Pending / COD (₹)</label>
                            <input type="number" id="pending_cod_amount" class="form-control text-end fw-bold font-monospace" value="0.00" readonly>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Cash Received & Change -->
                <div id="cash_received_section" class="card border-0 shadow-sm rounded-4 p-3 mb-3" style="background: #f0fdf4; border: 1.5px solid #bbf7d0 !important;">
                    <div class="row text-center g-2 align-items-end">
                        <div class="col-4">
                            <label for="cash_customer_gave" class="text-muted small d-block" style="font-size: 0.74rem;">Customer Gave</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" min="0" step="0.01" id="cash_customer_gave" class="form-control text-end fw-bold font-monospace" value="0.00" aria-label="Customer gave">
                            </div>
                        </div>
                        <div class="col-4">
                            <span class="text-muted small d-block" style="font-size: 0.74rem;">Amount Due</span>
                            <span class="fw-bold font-monospace fs-6 text-danger" id="display_amount_due">₹0.00</span>
                        </div>
                        <div class="col-4">
                            <span class="text-muted small d-block" style="font-size: 0.74rem;">Change to Return</span>
                            <span class="fw-bold font-monospace fs-6 text-success" id="display_change_return">₹0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Complete Sale Action Button & Version (Image 2 Bottom) -->
                <?php if (\App\Core\Auth::can('billing', !empty($editData) ? 'edit' : 'create')): ?>
                <button type="submit" id="btn_complete_sale" class="btn btn-secondary w-100 py-3 rounded-4 fw-bold fs-6 shadow-sm d-flex align-items-center justify-content-center gap-2 mb-1" style="border: none; transition: all 0.2s; background: #94a3b8; color: #ffffff;">
                    <i class="bi <?= !empty($editData) ? 'bi-save' : 'bi-printer' ?> fs-5"></i> <?= !empty($editData) ? 'Save Invoice Changes & Print' : 'Complete Sale & Print Invoice' ?>
                </button>
                <div id="checkout_helper_hint" class="text-center small py-1 mb-2" style="font-size: 0.76rem;">
                    <span class="text-muted"><i class="bi bi-info-circle me-1"></i> Scan or select products above to begin billing.</span>
                </div>
                <?php else: ?>
                <div class="alert alert-warning py-2 px-3 small rounded-3 text-center mb-2">
                    <i class="bi bi-lock-fill me-1"></i> View Only Access - Billing is disabled for this account.
                </div>
                <?php endif; ?>

                <div class="text-end text-muted small" style="font-size: 0.74rem;">
                    v1.0
                </div>
            </div>
        </div>
    </form>
</div>

<!-- ======================================================== -->
<!-- MODAL: INVOICE HISTORY                                    -->
<!-- ======================================================== -->
<div class="modal fade" id="modalInvoiceHistory" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i> Generated POS Sales Invoices</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Net Amount (₹)</th>
                                <th>Payment Mode</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No sales invoices generated yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?= !empty($inv['voided_at']) ? 'bg-danger' : 'bg-dark' ?> font-monospace"><?= htmlspecialchars($inv['invoice_no']) ?></span>
                                            <?php if (!empty($inv['voided_at'])): ?><small class="d-block text-danger fw-semibold">VOIDED</small><?php endif; ?>
                                        </td>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($inv['customer_name']) ?></td>
                                        <td class="small text-muted font-monospace"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= (int)$inv['items_count'] ?> items</span></td>
                                        <td class="fw-bold text-success font-monospace">₹<?= number_format($inv['net_amount'], 2) ?></td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary text-uppercase"><?= htmlspecialchars($posPaymentMethodLabel($inv['payment_method']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td class="text-end">
                                            <a href="<?= \App\Core\Controller::url('/m/billing/create?print_id=' . $inv['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-printer"></i> Print
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
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: INSTANT THERMAL / TAX RECEIPT PRINT VIEW           -->
<!-- ======================================================== -->
<?php if ($printInvoice): ?>
<div class="modal fade show d-block" id="modalPrintReceipt" tabindex="-1" style="background: rgba(0,0,0,0.65);" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content border-0 shadow-lg p-3 bg-white" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-1">
                <span class="badge <?= !empty($printInvoice['voided_at']) ? 'bg-danger' : 'bg-success' ?> text-uppercase fw-bold">
                    <i class="bi <?= !empty($printInvoice['voided_at']) ? 'bi-x-circle' : 'bi-check-circle' ?> me-1"></i>
                    <?= !empty($printInvoice['voided_at']) ? 'Invoice Voided' : 'Sale Completed' ?>
                </span>
                <a href="<?= \App\Core\Controller::url('/m/billing') ?>" class="btn-close" aria-label="Close"></a>
            </div>

            <!-- Printable Receipt Area -->
            <div class="p-3 border rounded-3 bg-light font-monospace small" id="thermal_receipt_print_area">
                <div class="text-center pb-2 border-bottom">
                    <?php if (!empty($printInvoice['concern_logo'])): ?>
                        <img src="<?= htmlspecialchars(\App\Services\CloudinaryService::url($printInvoice['concern_logo'])) ?>" alt="<?= htmlspecialchars($printInvoice['concern_name']) ?>" style="max-height: 48px; max-width: 140px; object-fit: contain; margin-bottom: 6px;">
                    <?php endif; ?>
                    <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($printInvoice['concern_name']) ?></h5>
                    <div style="font-size: 0.72rem;"><?= htmlspecialchars($printInvoice['concern_address'] ?: 'Retail Store Outlet') ?></div>
                    <div style="font-size: 0.72rem;">GSTIN: <?= htmlspecialchars($printInvoice['concern_gstin'] ?: '33AAACG1234F1Z8') ?></div>
                    <div style="font-size: 0.72rem;">TAX INVOICE / RETAIL RECEIPT</div>
                    <?php if (!empty($printInvoice['voided_at'])): ?>
                        <div class="text-danger fw-bold border border-danger rounded mt-1 px-2 py-1">VOIDED <?= htmlspecialchars(date('d M Y H:i', strtotime($printInvoice['voided_at'])), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="py-2 border-bottom" style="font-size: 0.76rem;">
                    <div class="d-flex justify-content-between thermal-receipt-customer-invoice" style="gap: 8px; align-items: flex-start;">
                        <div style="flex: 1; min-width: 0; overflow-wrap: anywhere;">
                            <strong>Customer:</strong>
                            <div><?= htmlspecialchars($printInvoice['customer_name']) ?></div>
                            <div><?= htmlspecialchars($printInvoice['customer_phone'] ?: '-') ?></div>
                        </div>
                        <div class="text-end" style="flex: 1; min-width: 0; overflow-wrap: anywhere;">
                            <div><strong>Inv No:</strong></div>
                            <div><?= htmlspecialchars($printInvoice['invoice_no']) ?></div>
                            <div><strong>Date:</strong> <?= date('d M Y', strtotime($printInvoice['invoice_date'])) ?></div>
                        </div>
                    </div>
                    <?php
                    $paymentStatusLabels = [
                        'paid' => 'PAID',
                        'partial' => 'PARTIALLY PAID / COD',
                        'unpaid' => 'UNPAID / COD',
                    ];
                    $paymentStatusLabel = $paymentStatusLabels[$printInvoice['payment_status']] ?? 'UNPAID / COD';
                    $printCouponDiscountAmount = max(0, (float)($printInvoice['coupon_discount_amount'] ?? 0));
                    $printItemDiscountAmount = max(0, (float)$printInvoice['discount_amount'] - $printCouponDiscountAmount);
                    $printGstType = 'both';
                    if (preg_match('/GST Type:\s*(both|igst|exempt)/i', (string)($printInvoice['notes'] ?? ''), $gstTypeMatch)) {
                        $printGstType = strtolower($gstTypeMatch[1]);
                    }
                    $printGstAmount = max(0, (float)$printInvoice['tax_amount']);
                    $printCgstAmount = $printGstType === 'both' ? round($printGstAmount / 2, 2) : 0;
                    $printSgstAmount = $printGstType === 'both' ? round($printGstAmount - $printCgstAmount, 2) : 0;
                    ?>
                    <div><strong>Payment:</strong> <?= htmlspecialchars($posPaymentMethodLabel($printInvoice['payment_method']), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($paymentStatusLabel, ENT_QUOTES, 'UTF-8') ?>)</div>
                </div>

                <div class="py-2 border-bottom">
                    <table class="w-100 thermal-receipt-items" style="font-size: 0.75rem; table-layout: fixed;">
                        <colgroup>
                            <col style="width: 48%;">
                            <col style="width: 10%;">
                            <col style="width: 21%;">
                            <col style="width: 21%;">
                        </colgroup>
                        <thead>
                            <tr class="text-muted border-bottom">
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">MRP<br>Price</th>
                                <th class="text-end">Bill<br>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($printItems as $it): ?>
                                <tr>
                                    <td style="overflow-wrap: anywhere;">
                                        <strong class="d-block"><?= htmlspecialchars($it['item_name']) ?></strong>
                                        <small>Size: <?= htmlspecialchars($it['size'] ?: '-') ?></small>
                                        <?php if (!empty($it['scanned_code_display'])): ?>
                                            <small class="d-block">Scanned code: <?= htmlspecialchars($it['scanned_code_display']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($it['batch_no'])): ?><small class="d-block">Batch <?= htmlspecialchars($it['batch_no']) ?></small><?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= (int)$it['qty'] ?></td>
                                    <td class="text-end" style="white-space: nowrap;">₹<?= number_format((float)$it['mrp_price'], 2) ?></td>
                                    <td class="text-end" style="white-space: nowrap;">₹<?= number_format($it['total_price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="py-2 border-bottom" style="font-size: 0.78rem;">
                    <div class="d-flex justify-content-between">
                        <span>Taxable Subtotal:</span>
                        <span>₹<?= number_format($printInvoice['total_amount'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>GST Tax Amount:</span>
                        <span>₹<?= number_format($printInvoice['tax_amount'], 2) ?></span>
                    </div>
                    <?php if ($printItemDiscountAmount > 0): ?>
                        <div class="d-flex justify-content-between text-danger">
                            <span>Item Discount (included in subtotal):</span>
                            <span>₹<?= number_format($printItemDiscountAmount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($printCouponDiscountAmount > 0): ?>
                        <div class="d-flex justify-content-between text-primary fw-semibold">
                            <span>Coupon (<?= htmlspecialchars($printInvoice['applied_coupon_code'] ?? '') ?>):</span>
                            <span>-₹<?= number_format($printCouponDiscountAmount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ((float)($printInvoice['shipping_amount'] ?? 0) > 0): ?>
                        <div class="d-flex justify-content-between">
                            <span>Shipping:</span>
                            <span>₹<?= number_format((float)$printInvoice['shipping_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-bold fs-6 text-dark">
                        <span>NET AMOUNT:</span>
                        <span>₹<?= number_format($printInvoice['net_amount'], 2) ?></span>
                    </div>
                    <?php if ($paymentStatusLabel !== 'PAID'): ?>
                        <div class="d-flex justify-content-between">
                            <span>Advance Paid:</span>
                            <span>₹<?= number_format((float)($printInvoice['paid_amount'] ?? 0), 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Pending / COD:</span>
                            <span>₹<?= number_format(max(0, (float)$printInvoice['net_amount'] - (float)($printInvoice['paid_amount'] ?? 0)), 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-center pt-2 text-muted" style="font-size: 0.7rem;">
                    Thank you for shopping with us!<br>
                    Goods once sold are exchangeable within 7 days.
                </div>
            </div>

            <div class="d-none" id="a4_invoice_print_area">
                <header class="a4-invoice-header">
                    <div>
                        <div class="a4-invoice-brand">
                        <?php if (!empty($printInvoice['concern_logo'])): ?>
                            <img src="<?= htmlspecialchars(\App\Services\CloudinaryService::url($printInvoice['concern_logo'])) ?>" alt="<?= htmlspecialchars($printInvoice['concern_name']) ?>">
                        <?php endif; ?>
                            <div class="a4-invoice-company-details">
                                <h1><?= htmlspecialchars($printInvoice['concern_name']) ?></h1>
                                <div><?= nl2br(htmlspecialchars($printInvoice['concern_address'] ?: '')) ?></div>
                                <div>Phone: <?= htmlspecialchars($printInvoice['concern_phone'] ?: '-') ?></div>
                                <div>GSTIN: <?= htmlspecialchars($printInvoice['concern_gstin'] ?: '-') ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="a4-invoice-title">
                        <h2>TAX INVOICE</h2>
                        <?php if (!empty($printInvoice['voided_at'])): ?>
                            <strong style="color:#b91c1c;border:1px solid #b91c1c;padding:4px 8px;display:inline-block;margin-top:8px;">VOIDED <?= htmlspecialchars(date('d M Y H:i', strtotime($printInvoice['voided_at'])), ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php endif; ?>
                        <div>Original for recipient</div>
                    </div>
                </header>

                <section class="a4-invoice-parties">
                    <section class="a4-invoice-customer">
                        <h3>Bill To</h3>
                        <strong><?= htmlspecialchars($printInvoice['customer_name']) ?></strong>
                        <div>Phone: <?= htmlspecialchars($printInvoice['customer_phone'] ?: '-') ?></div>
                        <div>Email: <?= htmlspecialchars($printInvoice['customer_email'] ?: '-') ?></div>
                        <div>Address: <?= nl2br(htmlspecialchars($printInvoice['customer_address'] ?: '-')) ?></div>
                        <div>GSTIN: <?= htmlspecialchars($printInvoice['customer_gstin'] ?: '-') ?></div>
                        <div>Billing For: <?= stripos((string)($printInvoice['notes'] ?? ''), 'Billing For: company') !== false ? 'Company' : 'Customer' ?></div>
                    </section>
                    <section class="a4-invoice-meta">
                        <h3>Invoice Details</h3>
                        <div><strong>Invoice No.</strong><span><?= htmlspecialchars($printInvoice['invoice_no']) ?></span></div>
                        <div><strong>Invoice Date</strong><span><?= date('d M Y', strtotime($printInvoice['invoice_date'])) ?></span></div>
                        <div><strong>Payment Method</strong><span><?= htmlspecialchars($posPaymentMethodLabel($printInvoice['payment_method']), ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div><strong>Payment Status</strong><span><?= htmlspecialchars($paymentStatusLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div><strong>GST Pricing</strong><span><?= stripos((string)($printInvoice['notes'] ?? ''), 'GST Price Mode: include') !== false ? 'GST Included' : 'GST Excluded' ?></span></div>
                        <?php if (!empty($printInvoice['is_direct_bill'])): ?>
                            <div><strong>Bill Type</strong><span>Direct Bill</span></div>
                        <?php endif; ?>
                    </section>
                </section>

                <table class="a4-invoice-items">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Description</th>
                            <th>Size</th>
                            <th class="number">Qty</th>
                            <th class="number">Unit Price</th>
                            <th class="number">Taxable Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($printItems as $index => $it): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <?= htmlspecialchars($it['item_name']) ?>
                                    <?php if (!empty($it['scanned_code_display'])): ?>
                                        <small>Scanned code: <?= htmlspecialchars($it['scanned_code_display']) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($it['batch_no'])): ?>
                                        <small>Batch <?= htmlspecialchars($it['batch_no']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($it['size'] ?: '-') ?></td>
                                <td class="number"><?= number_format((float)$it['qty'], 0) ?></td>
                                <td class="number">₹<?= number_format((float)$it['unit_price'], 2) ?></td>
                                <td class="number">₹<?= number_format((float)$it['total_price'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="a4-invoice-summary">
                    <div class="a4-invoice-discount">
                        <?php if ($printItemDiscountAmount > 0): ?>
                            Item Discount (included in taxable subtotal): ₹<?= number_format($printItemDiscountAmount, 2) ?>
                        <?php endif; ?>
                    </div>
                    <table>
                        <tr><th>Taxable Subtotal</th><td>₹<?= number_format((float)$printInvoice['total_amount'], 2) ?></td></tr>
                        <?php if ($printGstType === 'both'): ?>
                            <tr><th>CGST</th><td>₹<?= number_format($printCgstAmount, 2) ?></td></tr>
                            <tr><th>SGST</th><td>₹<?= number_format($printSgstAmount, 2) ?></td></tr>
                        <?php elseif ($printGstType === 'igst'): ?>
                            <tr><th>IGST</th><td>₹<?= number_format($printGstAmount, 2) ?></td></tr>
                        <?php else: ?>
                            <tr><th>GST Exempt</th><td>₹<?= number_format($printGstAmount, 2) ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Total GST</th><td>₹<?= number_format($printGstAmount, 2) ?></td></tr>
                        <?php if ($printCouponDiscountAmount > 0): ?>
                            <tr><th>Coupon Discount</th><td>-₹<?= number_format($printCouponDiscountAmount, 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if ((float)($printInvoice['shipping_amount'] ?? 0) > 0): ?>
                            <tr><th>Shipping</th><td>₹<?= number_format((float)$printInvoice['shipping_amount'], 2) ?></td></tr>
                        <?php endif; ?>
                        <tr class="a4-invoice-total"><th>Invoice Total</th><td>₹<?= number_format((float)$printInvoice['net_amount'], 2) ?></td></tr>
                        <tr><th>Amount Paid</th><td>₹<?= number_format((float)($printInvoice['paid_amount'] ?? 0), 2) ?></td></tr>
                        <tr><th>Balance Due</th><td>₹<?= number_format(max(0, (float)$printInvoice['net_amount'] - (float)($printInvoice['paid_amount'] ?? 0)), 2) ?></td></tr>
                    </table>
                </div>

                <footer class="a4-invoice-footer">
                    <div>Thank you for your business.</div>
                    <div class="a4-invoice-signature">Authorized Signatory</div>
                </footer>
            </div>

            <div class="d-flex flex-wrap justify-content-end gap-2 pt-3">
                <a href="<?= \App\Core\Controller::url('/m/billing') ?>" class="btn btn-outline-secondary px-3" style="border-radius: 9999px;">
                    Close
                </a>
                <button type="button" class="btn btn-outline-primary fw-bold shadow-sm" onclick="printReceiptArea('a4')" style="border-radius: 9999px;">
                    <i class="bi bi-file-earmark-pdf me-1"></i> A4 Format
                </button>
                <button type="button" class="btn btn-primary fw-bold shadow-sm" onclick="printReceiptArea('pos')" style="border-radius: 9999px; background: #4f46e5;">
                    <i class="bi bi-printer me-1"></i> POS Format
                </button>
                <?php if (stripos((string)($printInvoice['notes'] ?? ''), 'Billing For: company') !== false || !empty($printInvoice['billing_company_name'])): ?>
                    <a href="<?= \App\Core\Controller::url('/production/outward?pos_invoice_id=' . (int)$printInvoice['id']) ?>" class="btn btn-success fw-bold shadow-sm" style="border-radius: 9999px;">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Move to Outward
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- MODAL: SCANNER OPTIONS & SETTINGS                         -->
<!-- ======================================================== -->
<div class="modal fade" id="modalScannerOptions" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 490px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div style="width: 42px; height: 42px; background: #eff6ff; color: #2563eb; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                        <i class="bi bi-sliders2"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">POS Scanner Options</h5>
                        <small class="text-muted">Configure scanning hardware, audio feedback & behaviors</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 1. Preferred Scanner Mode -->
                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-2" style="letter-spacing: 0.05em; font-size: 0.72rem;">Default Input Device</label>
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="card h-100 p-2.5 text-center border scanner-opt-card active" id="card_opt_gun">
                                <i class="bi bi-upc-scan fs-3 text-primary mb-1"></i>
                                <span class="fw-bold small text-dark d-block" style="font-size: 0.8rem;">Barcode Gun</span>
                                <small class="text-muted" style="font-size: 0.68rem;">USB / Wireless</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card h-100 p-2.5 text-center border scanner-opt-card" id="card_opt_manual">
                                <i class="bi bi-keyboard fs-3 text-secondary mb-1"></i>
                                <span class="fw-bold small text-dark d-block" style="font-size: 0.8rem;">Manual</span>
                                <small class="text-muted" style="font-size: 0.68rem;">Type Product Code</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Audio & Beep Feedback -->
                <div class="mb-3 p-3 rounded-3 bg-light border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="fw-bold text-dark d-block small">Scan Audio Feedback (Beep)</span>
                            <small class="text-muted" style="font-size: 0.72rem;">Play cashier confirmation tone when product barcode is detected</small>
                        </div>
                        <div class="form-check form-switch fs-5 m-0">
                            <input class="form-check-input" type="checkbox" id="opt_sound_enabled" checked>
                        </div>
                    </div>
                    <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                        <small class="text-muted" style="font-size: 0.74rem;">Test Speaker Output:</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5" id="btnTestSound" style="font-size: 0.75rem;">
                            <i class="bi bi-volume-up-fill me-1 text-success"></i> Test Beep
                        </button>
                    </div>
                </div>

                <!-- 3. Quantity Increment Behavior -->
                <div class="mb-3 p-3 rounded-3 bg-light border">
                    <label class="form-label small fw-bold text-dark mb-1">Quantity Increment Rule</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="opt_qty_rule" id="qty_rule_auto" value="auto" checked>
                        <label class="form-check-label small" for="qty_rule_auto">
                            <strong>Continuous Scanning (+1 Piece automatically)</strong>
                            <div class="text-muted" style="font-size: 0.72rem;">Best for high-volume retail. Each barcode scan increases quantity by 1.</div>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="opt_qty_rule" id="qty_rule_prompt" value="prompt">
                        <label class="form-check-label small" for="qty_rule_prompt">
                            <strong>Prompt for Quantity on Scan</strong>
                            <div class="text-muted" style="font-size: 0.72rem;">Prompts for pieces after scanning each unique garment.</div>
                        </label>
                    </div>
                </div>

                <!-- 4. Continuous Auto-Focus -->
                <div class="p-3 rounded-3 bg-light border">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-bold text-dark d-block small">Continuous Auto-Focus</span>
                            <small class="text-muted" style="font-size: 0.72rem;">Keep cursor locked on barcode box for hands-free scanner guns</small>
                        </div>
                        <div class="form-check form-switch fs-5 m-0">
                            <input class="form-check-input" type="checkbox" id="opt_auto_focus" checked>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" id="btnSaveScannerOptions" style="background: #4f46e5;">Save Options</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- INTERACTIVE POS JAVASCRIPT LOGIC                         -->
<!-- ======================================================== -->
<style>
.pay-method-btn {
    background: #ffffff;
    border-color: #e2e8f0 !important;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}
.pay-method-btn:hover {
    border-color: #86efac !important;
    background: #f0fdf4;
}
.pay-method-btn[data-method="cash"] i {
    color: #059669;
}
.pay-method-btn[data-method="upi"] i {
    color: #7c3aed;
}
.pay-method-btn[data-method="card"] i {
    color: #f59e0b;
}
.pay-method-btn[data-method="split"] i {
    color: #06b6d4;
}
.pay-method-btn.active {
    border: 1.5px solid #16a34a !important;
    background: #f0fdf4 !important;
    color: #15803d !important;
    box-shadow: 0 0 0 1px #16a34a;
}
.pay-method-btn:focus-visible,
#split_auto_balance:focus-visible {
    outline: 2px solid #16a34a;
    outline-offset: 2px;
}
.split-payment-panel {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}
.payment-detail-card {
    padding: 0.65rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    background: #ffffff;
}
.payment-detail-card .form-label {
    font-size: 0.68rem;
    letter-spacing: 0.02em;
}
.scanner-opt-card {
    transition: all 0.2s;
    border-radius: 12px;
    background: #ffffff;
    cursor: pointer;
}
.scanner-opt-card:hover {
    border-color: #3b82f6 !important;
    background: #f8fafc;
}
.scanner-opt-card.active {
    border-color: #3b82f6 !important;
    background: #eff6ff !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}
@keyframes laserScan {
    0% { top: 5%; opacity: 0.9; }
    50% { top: 90%; opacity: 1; }
    100% { top: 5%; opacity: 0.9; }
}
.scanner-laser-line {
    position: absolute;
    left: 8%;
    width: 84%;
    height: 2px;
    background: #22c55e;
    box-shadow: 0 0 10px 2px #22c55e;
    animation: laserScan 2.4s infinite ease-in-out;
    pointer-events: none;
    z-index: 10;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const posEditData = <?= json_encode($editData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const isEditingPosInvoice = Boolean(posEditData);
    let cart = []; // Cart items array
    let currentPaymentMethod = posEditData ? posEditData.payment_method : 'cash';

    const qrInput = document.getElementById('pos_qr_input');
    const btnAddQr = document.getElementById('btn_add_qr');
    const cartTableBody = document.getElementById('cart_table_body');
    const cartEmptyState = document.getElementById('cart_empty_state');
    const cartItemsJson = document.getElementById('cart_items_json');
    const btnCompleteSale = document.getElementById('btn_complete_sale');
    const checkoutHelperHint = document.getElementById('checkout_helper_hint');
    const posInlineError = document.getElementById('pos_inline_error');
    const inputPaymentMethod = document.getElementById('input_payment_method');
    const onlineClassificationWrap = document.getElementById('online_classification_wrap');
    const toggleOnline = document.getElementById('toggle_online');
    const gstTypeSelect = document.getElementById('gst_type');
    const gstPriceModeSelect = document.getElementById('gst_price_mode');

    // Display fields
    const displayItemsCount = document.getElementById('display_items_count');
    const displayTaxableSubtotal = document.getElementById('display_taxable_subtotal');
    const badgeGstIncl = document.getElementById('badge_gst_incl');
    const displayCgst = document.getElementById('display_cgst');
    const displaySgst = document.getElementById('display_sgst');
    const badgeDiscount = document.getElementById('badge_discount');
    const displaySavings = document.getElementById('display_savings');
    const displayNetPayable = document.getElementById('display_net_payable');
    const shippingAmountInput = document.getElementById('pos_shipping_amount');
    const paymentStatusSelect = document.getElementById('pos_payment_status');
    const advancePaymentSection = document.getElementById('advance_payment_section');
    const advancePaidInput = document.getElementById('advance_paid_amount');
    const pendingCodAmount = document.getElementById('pending_cod_amount');

    // Cash received fields
    const cashSection = document.getElementById('cash_received_section');
    const cashCustomerGave = document.getElementById('cash_customer_gave');
    const displayAmountDue = document.getElementById('display_amount_due');
    const displayChangeReturn = document.getElementById('display_change_return');
    const splitPaymentSection = document.getElementById('split_payment_section');
    const splitCashAmount = document.getElementById('split_cash_amount');
    const splitUpiAmount = document.getElementById('split_upi_amount');
    const splitTotalAmount = document.getElementById('split_total_amount');
    const splitBalanceStatus = document.getElementById('split_balance_status');
    const splitAutoBalance = document.getElementById('split_auto_balance');

    // Customer fields
    const custMobile = document.getElementById('cust_mobile');
    const custName = document.getElementById('cust_name');
    const custAddress = document.getElementById('cust_address');
    const custLookupDropdown = document.getElementById('cust_lookup_dropdown');
    const custHistoryBadge = document.getElementById('customer_history_badge');
    const custHistoryBadgeText = document.getElementById('customer_history_badge_text');
    const billingPartyType = document.getElementById('billing_party_type');
    const billingPartyButtons = document.getElementById('billing_party_buttons');
    const companyDetailsWrap = document.getElementById('company_details_wrap');
    const companyName = document.getElementById('company_name');
    const companyAddress = document.getElementById('company_address');
    const companyPhone = document.getElementById('company_phone');
    const companyEmail = document.getElementById('company_email');
    const companyGstin = document.getElementById('company_gstin');
    const directBillWrap = document.getElementById('direct_bill_wrap');
    const directBillCheckbox = document.getElementById('is_direct_bill');
    const customerContactFields = document.getElementById('customer_contact_fields');
    const posCouponsPanel = document.getElementById('pos_coupons_panel');
    const posScannerCard = document.getElementById('pos_scanner_card');
    const posBillingForm = document.getElementById('pos_billing_form');

    function showPosInlineError(message, focusElement = null) {
        if (posInlineError) {
            posInlineError.textContent = message;
            posInlineError.classList.remove('d-none');
            posInlineError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (focusElement) {
            focusElement.focus();
        }
    }

    function clearPosInlineError() {
        if (posInlineError) {
            posInlineError.textContent = '';
            posInlineError.classList.add('d-none');
        }
    }

    function updateCompanyDetailsAvailability() {
        const enabled = billingPartyType.value === 'company';
        companyDetailsWrap.querySelectorAll('input').forEach(input => {
            input.disabled = !enabled;
        });
        companyName.required = enabled;
    }

    function clearCompanyDetails() {
        [companyName, companyAddress, companyPhone, companyEmail, companyGstin].forEach(input => {
            input.value = '';
        });
    }

    function updateCustomerBadge(orderCount) {
        if (!custHistoryBadge || !custHistoryBadgeText) return;
        const count = parseInt(orderCount, 10) || 0;
        const orderLabel = count === 1 ? '1 previous order' : `${count} previous orders`;
        custHistoryBadgeText.textContent = `Saved - ${orderLabel}`;
        custHistoryBadge.classList.remove('d-none');
    }

    function clearCustomerBadge() {
        if (!custHistoryBadge) return;
        custHistoryBadge.classList.add('d-none');
    }

    function hideCustomerDropdown() {
        if (!custLookupDropdown) return;
        custLookupDropdown.innerHTML = '';
        custLookupDropdown.classList.add('d-none');
    }

    function updateBillingPartyFields() {
        const isCompany = billingPartyType && billingPartyType.value === 'company';
        const customerChannelWrap = document.getElementById('customer_channel_wrap');
        billingPartyButtons.querySelectorAll('[data-billing-party]').forEach(button => {
            const isActive = button.dataset.billingParty === billingPartyType.value;
            button.classList.toggle('active', isActive);
            button.classList.toggle('btn-primary', isActive);
            button.classList.toggle('btn-outline-primary', !isActive);
        });
        customerChannelWrap.classList.toggle('d-none', isCompany);
        companyDetailsWrap.classList.toggle('d-none', !isCompany);
        directBillWrap.classList.toggle('d-none', !isCompany);
        customerContactFields.classList.toggle('d-none', isCompany);
        posCouponsPanel.classList.toggle('d-none', isCompany);
        posScannerCard.classList.remove('d-none');

        updateCompanyDetailsAvailability();
        customerContactFields.querySelectorAll('input').forEach(input => {
            input.disabled = isCompany;
        });

        clearCustomerBadge();
        hideCustomerDropdown();

        if (isCompany) {
            const channelSelect = document.getElementById('pos_customer_channel');
            const directChannel = channelSelect && Array.from(channelSelect.options).find(option => option.value === 'direct');
            if (channelSelect && directChannel && channelSelect.value !== 'direct') {
                channelSelect.value = 'direct';
                channelSelect.dispatchEvent(new Event('change'));
                document.getElementById('customerChannelDropdownBtn').textContent = directChannel.textContent.trim();
            }
            document.getElementById('pos_coupon_select').value = '';
            document.getElementById('coupon_mode_direct').checked = true;
            document.getElementById('coupon_mode_product').checked = false;
            document.getElementById('applied_coupon_id').value = '';
            document.getElementById('applied_coupon_mode').value = 'DIRECT';
            const couponFeedback = document.getElementById('pos_coupon_feedback');
            couponFeedback.classList.add('d-none');
            couponFeedback.textContent = '';
            cart.forEach(item => {
                delete item.use_coupon;
            });
            if (typeof window.renderCart === 'function') {
                window.renderCart();
            } else {
                calculateCartTotals();
            }
        } else {
            directBillCheckbox.checked = false;
            custMobile.value = '';
            custName.value = '';
            custAddress.value = '';
            clearCompanyDetails();
            updateCompanyDetailsAvailability();
        }
    }

    billingPartyType.addEventListener('change', updateBillingPartyFields);
    billingPartyButtons.querySelectorAll('[data-billing-party]').forEach(button => {
        button.addEventListener('click', function() {
            billingPartyType.value = this.dataset.billingParty;
            billingPartyType.dispatchEvent(new Event('change'));
        });
    });
    if (billingPartyType) updateBillingPartyFields();

    function getVariantCartQuantity(variantId, skipIndex = -1) {
        return cart.reduce((total, item, index) => {
            return index !== skipIndex && String(item.id) === String(variantId)
                ? total + (parseInt(item.qty, 10) || 0)
                : total;
        }, 0);
    }

    // 2. Mobile Number Auto-Lookup & Order History Suggestions
    function selectCustomer(cust) {
        if (!cust) return;
        if (custMobile) custMobile.value = cust.phone || '';
        if (custName) custName.value = cust.name || '';
        if (custAddress) custAddress.value = cust.address || cust.city || '';
        updateCustomerBadge(cust.order_count || 0);
        hideCustomerDropdown();
    }

    let customerLookupTimer = null;
    function performCustomerLookup(query, autoSelectExact = false) {
        const trimmed = (query || '').trim();
        if (trimmed.length < 3) {
            hideCustomerDropdown();
            clearCustomerBadge();
            return;
        }

        fetch('<?= \App\Core\Controller::url('/api/pos/customer-lookup?phone=') ?>' + encodeURIComponent(trimmed))
            .then(res => res.json())
            .then(data => {
                if (data.found && data.customer) {
                    if (autoSelectExact || (trimmed.length === 10 && data.customer.phone === trimmed)) {
                        selectCustomer(data.customer);
                    } else {
                        renderCustomerDropdown(data.customers || [data.customer]);
                    }
                } else {
                    hideCustomerDropdown();
                    clearCustomerBadge();
                }
            })
            .catch(() => {
                hideCustomerDropdown();
            });
    }

    function renderCustomerDropdown(customers) {
        if (!custLookupDropdown || !customers || !customers.length) {
            hideCustomerDropdown();
            return;
        }

        custLookupDropdown.innerHTML = '';
        customers.forEach(c => {
            const item = document.createElement('div');
            item.className = 'cust-suggest-item p-2 rounded-2 border-bottom';
            item.style.cursor = 'pointer';
            item.style.transition = 'background 0.15s ease';
            const orders = parseInt(c.order_count, 10) || 0;
            const ordersText = orders === 1 ? '1 previous order' : `${orders} previous orders`;

            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark" style="font-size: 0.88rem;">${escapeHtml(c.name || 'Customer')}</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size: 0.72rem;">${escapeHtml(c.phone || '')}</span>
                </div>
                <div class="text-muted small text-truncate mt-0.5" style="font-size: 0.75rem;">${escapeHtml(c.address || c.city || 'No address on file')}</div>
                <div class="text-success small fw-semibold mt-1" style="font-size: 0.76rem;">
                    <i class="bi bi-bag-check me-1"></i>${ordersText}
                </div>
            `;

            item.addEventListener('mouseenter', () => item.style.backgroundColor = '#f1f5f9');
            item.addEventListener('mouseleave', () => item.style.backgroundColor = 'transparent');
            item.addEventListener('click', (e) => {
                e.preventDefault();
                selectCustomer(c);
            });

            custLookupDropdown.appendChild(item);
        });

        custLookupDropdown.classList.remove('d-none');
    }

    if (custMobile) {
        custMobile.addEventListener('input', function() {
            const phone = this.value.trim();
            clearTimeout(customerLookupTimer);
            if (phone.length < 3) {
                hideCustomerDropdown();
                clearCustomerBadge();
                return;
            }
            if (phone.length === 10) {
                performCustomerLookup(phone, true);
            } else {
                customerLookupTimer = setTimeout(() => {
                    performCustomerLookup(phone, false);
                }, 200);
            }
        });

        custMobile.addEventListener('focus', function() {
            const phone = this.value.trim();
            if (phone.length >= 3) {
                performCustomerLookup(phone, false);
            }
        });
    }

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        if (custLookupDropdown && !custLookupDropdown.contains(e.target) && e.target !== custMobile) {
            hideCustomerDropdown();
        }
    });

    // Prevent Enter key in customer inputs from submitting the billing form or redirecting to old bill!
    if (posBillingForm) {
        posBillingForm.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const tag = e.target ? e.target.tagName : '';
                const id = e.target ? e.target.id : '';
                if (tag === 'INPUT' && id !== 'qr_input' && e.target.type !== 'submit') {
                    e.preventDefault();
                    if (id === 'cust_mobile') {
                        const firstItem = custLookupDropdown ? custLookupDropdown.querySelector('.cust-suggest-item') : null;
                        if (firstItem && !custLookupDropdown.classList.contains('d-none')) {
                            firstItem.click();
                        } else if (custMobile.value.trim().length === 10) {
                            performCustomerLookup(custMobile.value.trim(), true);
                            if (custName && !custName.value.trim()) {
                                custName.focus();
                            } else if (qrInput) {
                                qrInput.focus();
                            }
                        } else if (custName) {
                            custName.focus();
                        }
                    } else if (id === 'cust_name' && custAddress) {
                        custAddress.focus();
                    } else if (id === 'cust_address' && qrInput) {
                        qrInput.focus();
                    }
                    return false;
                }
            }
        });
    }

    // Clear print_id from URL so subsequent refresh does not reload the old bill receipt modal
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('print_id')) {
            url.searchParams.delete('print_id');
            window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : ''));
        }
    }

    // -----------------------------------------------------------------
    // SCANNER OPTIONS & HARDWARE CONFIGURATION
    // -----------------------------------------------------------------
    let scannerSettings = {
        mode: 'gun', // 'gun', 'manual'
        sound: true,
        qtyRule: 'auto', // 'auto', 'prompt'
        autoFocus: true
    };

    try {
        const saved = localStorage.getItem('invento_pos_scanner_settings');
        if (saved) {
            scannerSettings = Object.assign(scannerSettings, JSON.parse(saved));
            if (scannerSettings.mode === 'camera') scannerSettings.mode = 'gun';
        }
    } catch(e) {}

    function playPosBeep(type = 'success') {
        if (!scannerSettings.sound) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            if (type === 'success') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1400, ctx.currentTime);
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12);
                osc.start();
                osc.stop(ctx.currentTime + 0.12);
            } else {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(280, ctx.currentTime);
                gain.gain.setValueAtTime(0.25, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                osc.start();
                osc.stop(ctx.currentTime + 0.25);
            }
        } catch(e) {}
    }

    function updateScannerUI() {
        const lblMode = document.getElementById('label_active_mode');
        const iconMode = document.getElementById('icon_active_mode');
        const activeIcon = document.getElementById('active_input_icon');

        if (scannerSettings.mode === 'manual') {
            if (lblMode) lblMode.textContent = 'Manual Typing';
            if (iconMode) iconMode.className = 'bi bi-keyboard text-secondary';
            if (activeIcon) activeIcon.className = 'bi bi-keyboard';
        } else {
            if (lblMode) lblMode.textContent = 'Barcode Gun';
            if (iconMode) iconMode.className = 'bi bi-upc text-primary';
            if (activeIcon) activeIcon.className = 'bi bi-qr-code';
        }

        const iconBeep = document.getElementById('icon_quick_beep');
        const lblBeep = document.getElementById('label_quick_beep');
        if (iconBeep && lblBeep) {
            if (scannerSettings.sound) {
                iconBeep.className = 'bi bi-volume-up-fill text-success';
                lblBeep.textContent = 'Beep ON';
                lblBeep.className = 'text-success fw-semibold';
            } else {
                iconBeep.className = 'bi bi-volume-mute-fill text-muted';
                lblBeep.textContent = 'Muted';
                lblBeep.className = 'text-muted fw-normal';
            }
        }

        const badgeQty = document.getElementById('badge_quick_qty');
        if (badgeQty) {
            badgeQty.textContent = (scannerSettings.qtyRule === 'prompt') ? 'Ask Qty' : '+1 / Scan';
            badgeQty.className = (scannerSettings.qtyRule === 'prompt') ? 'badge bg-warning text-dark border' : 'badge bg-light text-dark border';
        }

        const optSound = document.getElementById('opt_sound_enabled');
        if (optSound) optSound.checked = scannerSettings.sound;
        const optFocus = document.getElementById('opt_auto_focus');
        if (optFocus) optFocus.checked = scannerSettings.autoFocus;

        const qtyAuto = document.getElementById('qty_rule_auto');
        const qtyPrompt = document.getElementById('qty_rule_prompt');
        if (scannerSettings.qtyRule === 'prompt') {
            if (qtyPrompt) qtyPrompt.checked = true;
        } else {
            if (qtyAuto) qtyAuto.checked = true;
        }

        ['gun', 'manual'].forEach(k => {
            const card = document.getElementById('card_opt_' + k);
            if (card) {
                if ((k === 'gun' && scannerSettings.mode === 'gun') ||
                    (k === 'manual' && scannerSettings.mode === 'manual')) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            }
        });
    }

    function saveSettings() {
        try {
            localStorage.setItem('invento_pos_scanner_settings', JSON.stringify(scannerSettings));
        } catch(e) {}
    }

    window.selectScannerDevice = function(mode) {
        scannerSettings.mode = mode;
        saveSettings();
        updateScannerUI();
    };

    ['gun', 'manual'].forEach(k => {
        const card = document.getElementById('card_opt_' + k);
        if (card) {
            card.addEventListener('click', () => {
                selectScannerDevice(k === 'gun' ? 'gun' : 'manual');
            });
        }
    });

    const btnQuickToggleBeep = document.getElementById('btn_quick_toggle_beep');
    if (btnQuickToggleBeep) {
        btnQuickToggleBeep.addEventListener('click', function() {
            scannerSettings.sound = !scannerSettings.sound;
            playPosBeep('success');
            saveSettings();
            updateScannerUI();
        });
    }

    const btnQuickToggleQty = document.getElementById('btn_quick_toggle_qty');
    if (btnQuickToggleQty) {
        btnQuickToggleQty.addEventListener('click', function() {
            scannerSettings.qtyRule = (scannerSettings.qtyRule === 'prompt') ? 'auto' : 'prompt';
            saveSettings();
            updateScannerUI();
        });
    }

    const badgeModeClick = document.getElementById('badge_scanner_mode');
    const btnOpenOptions = document.getElementById('btnOpenScannerOptions');
    function showScannerOptionsModal() {
        const modalEl = document.getElementById('modalScannerOptions');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    if (badgeModeClick) badgeModeClick.addEventListener('click', showScannerOptionsModal);
    if (btnOpenOptions) btnOpenOptions.addEventListener('click', showScannerOptionsModal);

    const btnTestSound = document.getElementById('btnTestSound');
    if (btnTestSound) {
        btnTestSound.addEventListener('click', function() {
            const prev = scannerSettings.sound;
            scannerSettings.sound = true;
            playPosBeep('success');
            scannerSettings.sound = prev;
        });
    }

    const btnSaveOpts = document.getElementById('btnSaveScannerOptions');
    if (btnSaveOpts) {
        btnSaveOpts.addEventListener('click', function() {
            const optSound = document.getElementById('opt_sound_enabled');
            if (optSound) scannerSettings.sound = optSound.checked;
            const optFocus = document.getElementById('opt_auto_focus');
            if (optFocus) scannerSettings.autoFocus = optFocus.checked;
            const qtyPrompt = document.getElementById('qty_rule_prompt');
            if (qtyPrompt) scannerSettings.qtyRule = qtyPrompt.checked ? 'prompt' : 'auto';

            saveSettings();
            updateScannerUI();

            const modalEl = document.getElementById('modalScannerOptions');
            if (modalEl && window.bootstrap) {
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }
        });
    }

    updateScannerUI();

    // 3. Scan & Add Product Function
    function addProductByQuery(query, fromCamera = false) {
        if (!query) return;
        query = query.trim();
        if (!query) return;

        const scannerStatus = document.getElementById('scanner_status_text');
        if (scannerStatus) scannerStatus.textContent = 'Searching item...';

        fetch('<?= \App\Core\Controller::url('/api/pos/variant-lookup?q=') ?>' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                if (data.success && data.variant) {
                    const v = data.variant;
                    if (cart.length > 0 && Number(cart[0].concern_id) !== Number(v.concern_id)) {
                        playPosBeep('error');
                        showPosInlineError('A POS bill can contain products from only one concern. Complete this bill before adding products from another concern.');
                        return;
                    }
                    const availableQty = Math.max(0, Math.floor(Number(v.available_qty) || 0));
                    if (availableQty <= 0) {
                        playPosBeep('error');
                        if (scannerStatus) scannerStatus.textContent = 'Out of stock.';
                        showPosInlineError('No available stock for this product.');
                        return;
                    }
                    if (v.gst_pct === null || !Number.isFinite(Number(v.gst_pct))) {
                        showPosInlineError('Set and verify this product\'s GST rate in the catalog before billing it.');
                        return;
                    }
                    let qtyToAdd = 1;
                    if (scannerSettings.qtyRule === 'prompt') {
                        qtyToAdd = 1;
                        showPosInlineError('Quantity prompt mode is disabled. The scanned item was added with quantity 1.');
                    }

                    const currentQty = getVariantCartQuantity(v.id);
                    const isComplementary = Boolean(v.is_complementary);
                    if (currentQty + qtyToAdd > availableQty) {
                        const remainingQty = Math.max(0, availableQty - currentQty);
                        playPosBeep('error');
                        if (scannerStatus) {
                            scannerStatus.textContent = remainingQty > 0
                                ? 'Only ' + remainingQty + ' more available.'
                                : 'Available stock already in cart.';
                        }
                        showPosInlineError('Available stock: ' + availableQty + '. Already in cart: ' + currentQty + '. You can add only ' + remainingQty + ' more.');
                        return;
                    }

                    const existingIndex = cart.findIndex(item => String(item.id) === String(v.id) && !item.batch_id);
                    if (existingIndex > -1) {
                        cart[existingIndex].qty += qtyToAdd;
                        cart[existingIndex].available_qty = availableQty;
                        if (!Array.isArray(cart[existingIndex].scanned_codes)) {
                            cart[existingIndex].scanned_codes = [];
                        }
                        if (!cart[existingIndex].scanned_codes.includes(query)) {
                            cart[existingIndex].scanned_codes.push(query);
                        }
                    } else {
                        cart.push({
                            id: v.id,
                            concern_id: Number(v.concern_id),
                            name: v.name,
                            product_code: v.product_code,
                            scanned_codes: [query],
                            size: v.size,
                            hsn_code: v.hsn_code || '',
                            price: Number(v.price),
                            is_complementary: isComplementary,
                            mrp: v.mrp,
                            gst_pct: Number(v.gst_pct),
                            discount: 0.00,
                            qty: qtyToAdd,
                            available_qty: availableQty
                        });
                    }
                    if (getSelectedCouponMode() === 'PRODUCT_BUY') {
                        setCouponProduct(existingIndex > -1 ? existingIndex : cart.length - 1);
                    }

                    playPosBeep('success');

                    if (fromCamera) {
                        logCameraScanChip(v, true);
                    }

                    if (scannerStatus) {
                        scannerStatus.textContent = 'Added: ' + v.name + ' (+' + qtyToAdd + ')';
                        setTimeout(() => { scannerStatus.textContent = 'Ready for next scan...'; }, 2000);
                    }

                    qrInput.value = '';
                    renderCart();

                    if (scannerSettings.autoFocus && !fromCamera) {
                        qrInput.focus();
                    }
                } else {
                    playPosBeep('error');
                    if (fromCamera) {
                        logCameraScanChip(null, false);
                        if (camStatusDisplay) {
                            camStatusDisplay.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle"></i> Not found: ' + query + '</span>';
                        }
                    } else {
                        showPosInlineError(data.message || 'Product not found in this concern catalog.');
                    }
                    if (scannerStatus) scannerStatus.textContent = 'Item not found.';
                    if (scannerSettings.autoFocus && !fromCamera) {
                        qrInput.focus();
                    }
                }
            })
            .catch(err => {
                playPosBeep('error');
                const message = err && err.message ? err.message : String(err);
                if (fromCamera) {
                    if (camStatusDisplay) camStatusDisplay.textContent = 'Could not add scanned product: ' + message;
                } else {
                    showPosInlineError('Could not add scanned product: ' + message);
                }
                if (scannerStatus) scannerStatus.textContent = message;
            });
    }

    // QR Input Submit triggers
    if (btnAddQr && qrInput) {
        btnAddQr.addEventListener('click', () => addProductByQuery(qrInput.value));
        qrInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addProductByQuery(this.value);
            }
        });
    }

    // 4. Render Cart Function
    function calculateLineAmounts(item) {
        const gstType = gstTypeSelect ? gstTypeSelect.value : 'both';
        const priceMode = gstPriceModeSelect ? gstPriceModeSelect.value : 'exclude';
        const gstRate = gstType === 'exempt' ? 0 : (parseFloat(item.gst_pct) || 0);
        const unitPrice = item.is_complementary
            ? (parseFloat(item.price) || 0)
            : (item.custom_unit_price !== undefined && item.custom_unit_price !== null
                ? (parseFloat(item.custom_unit_price) || 0)
                : (parseFloat(item.price) || 0));
        const qty = Math.max(1, parseInt(item.qty, 10) || 1);
        const grossAmount = Math.round(unitPrice * qty * 100) / 100;
        const discount = Math.min(grossAmount, Math.max(0, parseFloat(item.discount) || 0));
        const discountedAmount = Math.round(Math.max(0, grossAmount - discount) * 100) / 100;

        if (item.is_complementary) {
            const baseUnitPrice = (priceMode === 'include' && gstRate > 0)
                ? Math.round((unitPrice / (1 + gstRate / 100)) * 100) / 100
                : Math.round(unitPrice * 100) / 100;
            return {
                baseUnitPrice,
                taxableAmount: 0,
                gstAmount: 0,
                discount: 0,
                billedAmount: 0,
                gstRate
            };
        }

        if (priceMode === 'include' && gstRate > 0) {
            const baseUnitPrice = Math.round((unitPrice / (1 + gstRate / 100)) * 100) / 100;
            const taxableAmount = Math.round((discountedAmount / (1 + gstRate / 100)) * 100) / 100;
            const gstAmount = Math.round((discountedAmount - taxableAmount) * 100) / 100;
            return { baseUnitPrice, taxableAmount, gstAmount, discount, billedAmount: discountedAmount, gstRate };
        }

        const taxableAmount = discountedAmount;
        const gstAmount = Math.round((taxableAmount * gstRate / 100) * 100) / 100;
        return {
            baseUnitPrice: Math.round(unitPrice * 100) / 100,
            taxableAmount,
            gstAmount,
            discount,
            billedAmount: Math.round((taxableAmount + gstAmount) * 100) / 100,
            gstRate
        };
    }

    function maxQuantityForCartLine(index) {
        const item = cart[index];
        const otherVariantQty = getVariantCartQuantity(item.id, index);
        const stockLimit = Math.max(0, Math.floor(Number(item.available_qty) - otherVariantQty));
        return item.batch_id
            ? Math.min(stockLimit, Math.floor(Number(item.batch_remaining_qty) || 0))
            : stockLimit;
    }

    window.renderCart = function() {
        if (!cartTableBody) return;

        cartTableBody.innerHTML = '';
        if (cart.length > 0 && getSelectedCouponMode() === 'PRODUCT_BUY' && document.getElementById('pos_coupon_select')?.value) {
            ensureCouponProductSelection();
        }

        if (cart.length === 0) {
            cartEmptyState.classList.remove('d-none');
            btnCompleteSale.style.background = '#94a3b8';
            btnCompleteSale.style.color = '#ffffff';
            btnCompleteSale.style.cursor = 'not-allowed';
            btnCompleteSale.style.boxShadow = 'none';
            if (checkoutHelperHint) {
                checkoutHelperHint.innerHTML = '<span class="text-secondary"><i class="bi bi-info-circle me-1"></i> Scan or enter a product above to begin billing.</span>';
            }
        } else {
            cartEmptyState.classList.add('d-none');
            btnCompleteSale.style.background = '#4f46e5';
            btnCompleteSale.style.color = '#ffffff';
            btnCompleteSale.style.cursor = 'pointer';
            btnCompleteSale.style.boxShadow = '0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -2px rgba(79, 70, 229, 0.2)';
            if (checkoutHelperHint) {
                checkoutHelperHint.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i> Ready to checkout. Click Complete Sale to continue.</span>';
            }

            cart.forEach((item, index) => {
                const tr = document.createElement('tr');
                const line = calculateLineAmounts(item);
                const qty = Math.max(1, parseInt(item.qty, 10) || 1);
                const discount = parseFloat(item.discount) || 0;
                const qtyMax = maxQuantityForCartLine(index);
                const scannedCodes = Array.isArray(item.scanned_codes) ? item.scanned_codes : [];
                const isCouponProduct = item.use_coupon
                    && getSelectedCouponMode() === 'PRODUCT_BUY'
                    && Boolean(document.getElementById('pos_coupon_select')?.value);
                tr.dataset.index = index;

                tr.innerHTML = `
                    <td class="text-muted fw-bold">${index + 1}</td>
                    <td>
                        <strong class="text-dark d-block">${escapeHtml(item.name)}${isCouponProduct ? ' <span class="badge bg-success-subtle text-success border border-success-subtle">Coupon product</span>' : ''}</strong>
                        <small class="text-muted font-monospace">Size: ${escapeHtml(item.size)}${item.is_complementary ? ' &bull; Complimentary' : ''}${item.batch_no ? ' &bull; Batch: ' + escapeHtml(item.batch_no) : ''}</small>
                        ${scannedCodes.length ? `<small class="text-primary d-block font-monospace">Scanned code: ${escapeHtml(scannedCodes.join(', '))}</small>` : ''}
                        <div class="form-check form-check-inline mt-1 coupon-item-toggle ${getSelectedCouponMode() === 'PRODUCT_BUY' && document.getElementById('pos_coupon_select')?.value ? '' : 'd-none'}">
                            <input class="form-check-input item-use-coupon-cb" type="radio" name="coupon_product_line" id="use_coupon_cb_${index}" data-index="${index}" ${item.use_coupon ? 'checked' : ''}>
                            <label class="form-check-label small text-primary fw-semibold" for="use_coupon_cb_${index}">Apply coupon to this product</label>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border font-monospace d-inline-block" style="white-space: normal; overflow-wrap: anywhere;">${escapeHtml(item.hsn_code)}</span></td>
                    <td>
                        <input type="number" min="1" max="${qtyMax}" step="1" class="form-control form-control-sm text-center font-monospace item-qty-input"
                               value="${qty}" data-index="${index}" aria-label="Quantity for ${escapeHtml(item.name)}" style="width: 100%; max-width: 40px; min-width: 0; font-size: 0.75rem; padding: 0.15rem 0.1rem;">
                    </td>
                    <td class="fw-bold font-monospace item-base-price" style="font-size: 0.72rem;">₹${line.baseUnitPrice.toFixed(2)}</td>
                    <td>
                        <span class="badge bg-light text-primary border">${line.gstRate}%</span>
                    </td>
                    <td>
                        <input type="number" min="0" step="1" class="form-control form-control-sm text-end font-monospace item-discount-input" 
                               value="${discount.toFixed(0)}" data-index="${index}" style="width: 100%; max-width: 48px; min-width: 0; font-size: 0.75rem; padding: 0.15rem 0.2rem;">
                    </td>
                    <td>
                        <div class="input-group input-group-sm justify-content-end" style="width: 100%; min-width: 0; flex-wrap: nowrap;">
                            <span class="input-group-text bg-light px-1.5 flex-shrink-0" style="font-size: 0.72rem; white-space: nowrap;">₹</span>
                            <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end font-monospace fw-bold text-dark item-billed-price-input"
                                   value="${line.billedAmount.toFixed(2)}" data-index="${index}" aria-label="Billed price for ${escapeHtml(item.name)}"
                                   style="width: 1%; flex: 1 1 0; min-width: 0; font-size: 0.72rem; padding: 0.15rem 0.2rem;" ${item.is_complementary ? 'readonly' : ''}>
                        </div>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeCartItem(${index})" title="Remove item">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </td>
                `;
                cartTableBody.appendChild(tr);
            });

            document.querySelectorAll('.item-use-coupon-cb').forEach(cb => {
                cb.addEventListener('change', function() {
                    if (!this.checked) return;
                    const idx = parseInt(this.getAttribute('data-index'));
                    setCouponProduct(idx);
                    renderCart();
                });
            });

            // Attach discount change listeners
            document.querySelectorAll('.item-discount-input').forEach(input => {
                input.addEventListener('change', function() {
                    const idx = parseInt(this.getAttribute('data-index'));
                    const qty = Math.max(1, parseInt(cart[idx].qty, 10) || 1);
                    const unitPrice = cart[idx].custom_unit_price !== undefined ? (parseFloat(cart[idx].custom_unit_price) || 0) : (parseFloat(cart[idx].price) || 0);
                    const maxDiscount = Math.max(0, unitPrice * qty);
                    cart[idx].discount = Math.min(maxDiscount, Math.max(0, parseFloat(this.value) || 0));
                    renderCart();
                });
            });

            // Attach billed price change listeners
            document.querySelectorAll('.item-billed-price-input').forEach(input => {
                input.addEventListener('change', function() {
                    const idx = parseInt(this.getAttribute('data-index'));
                    if (cart[idx].is_complementary) {
                        this.value = '0.00';
                        return;
                    }
                    const targetBilled = Math.max(0, parseFloat(this.value) || 0);
                    const qty = Math.max(1, parseInt(cart[idx].qty, 10) || 1);
                    const gstType = gstTypeSelect ? gstTypeSelect.value : 'both';
                    const priceMode = gstPriceModeSelect ? gstPriceModeSelect.value : 'exclude';
                    const gstRate = gstType === 'exempt' ? 0 : (parseFloat(cart[idx].gst_pct) || 0);
                    const basePrice = parseFloat(cart[idx].price) || 0;
                    const grossAmount = Math.round(basePrice * qty * 100) / 100;
                    const targetDiscounted = (priceMode === 'exclude' && gstRate > 0)
                        ? (targetBilled / (1 + gstRate / 100))
                        : targetBilled;
                    if (targetDiscounted > grossAmount + 0.005) {
                        cart[idx].custom_unit_price = Math.round((targetDiscounted / qty) * 100) / 100;
                        cart[idx].discount = 0;
                    } else {
                        delete cart[idx].custom_unit_price;
                        cart[idx].discount = Math.round(Math.max(0, grossAmount - targetDiscounted) * 100) / 100;
                    }
                    renderCart();
                });
            });

            document.querySelectorAll('.item-qty-input').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = parseInt(this.getAttribute('data-index'));
                    const maxQty = maxQuantityForCartLine(idx);
                    const enteredQty = parseInt(this.value, 10);
                    if (Number.isFinite(enteredQty) && enteredQty > maxQty) {
                        this.value = maxQty;
                    }
                    if (maxQty > 0) {
                        cart[idx].qty = Math.min(maxQty, Math.max(1, parseInt(this.value, 10) || 1));
                        calculateCartTotals();
                    }
                });
                input.addEventListener('change', function() {
                    const idx = parseInt(this.getAttribute('data-index'));
                    const maxQty = maxQuantityForCartLine(idx);
                    const requestedQty = Math.max(1, parseInt(this.value, 10) || 1);
                    if (requestedQty > maxQty) {
                        showPosInlineError('Only ' + maxQty + ' units are available for this item.');
                    }
                    cart[idx].qty = Math.min(requestedQty, maxQty);
                    if (cart[idx].qty < 1) {
                        cart.splice(idx, 1);
                        renderCart();
                        return;
                    }
                    const maxDiscount = Math.max(0, (parseFloat(cart[idx].price) || 0) * cart[idx].qty);
                    cart[idx].discount = Math.min(maxDiscount, Math.max(0, parseFloat(cart[idx].discount) || 0));
                    renderCart();
                });
            });
        }

        calculateCartTotals();
    };

    window.removeCartItem = function(index) {
        cart.splice(index, 1);
        renderCart();
    };

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    const customerChannelSelect = document.getElementById('pos_customer_channel');
    const orderChannelPaymentFields = document.getElementById('order_channel_payment_fields');

    function isOrderChannelSelected() {
        if (isEditingPosInvoice) return true;
        if (!customerChannelSelect) return false;
        const opt = customerChannelSelect.options[customerChannelSelect.selectedIndex];
        return Boolean(opt && opt.dataset && opt.dataset.orderChannel === '1');
    }

    function updateCustomerChannelFields() {
        const showOrderFields = isOrderChannelSelected();
        if (orderChannelPaymentFields) {
            orderChannelPaymentFields.style.display = showOrderFields ? 'block' : 'none';
        }
        if (!showOrderFields) {
            if (shippingAmountInput) shippingAmountInput.value = '0.00';
            if (paymentStatusSelect) paymentStatusSelect.value = 'paid';
            if (advancePaidInput) advancePaidInput.value = '0.00';
            if (pendingCodAmount) pendingCodAmount.value = '0.00';
        }
        calculateCartTotals();
    }

    if (customerChannelSelect) {
        customerChannelSelect.addEventListener('change', updateCustomerChannelFields);
    }

    // 5. Calculate Cart Totals
    function getPaidAmount(netPayable) {
        if (!isOrderChannelSelected()) return netPayable;
        const status = paymentStatusSelect ? paymentStatusSelect.value : 'paid';
        if (status === 'unpaid') return 0;
        if (status === 'partial') {
            return Math.min(netPayable, Math.max(0, parseFloat(advancePaidInput ? advancePaidInput.value : '0') || 0));
        }
        return netPayable;
    }

    function updatePaymentStatusDetails(netPayable) {
        const showOrderFields = isOrderChannelSelected();
        const status = showOrderFields && paymentStatusSelect ? paymentStatusSelect.value : 'paid';
        if (advancePaymentSection) {
            advancePaymentSection.style.display = showOrderFields ? 'flex' : 'none';
        }
        if (advancePaidInput) {
            advancePaidInput.disabled = !showOrderFields || status === 'unpaid' || status === 'paid';
            if (!showOrderFields || status === 'unpaid') {
                advancePaidInput.value = '0.00';
            } else if (status === 'paid') {
                advancePaidInput.value = netPayable.toFixed(2);
            }
        }
        const paidAmount = getPaidAmount(netPayable);
        if (pendingCodAmount) pendingCodAmount.value = Math.max(0, netPayable - paidAmount).toFixed(2);
        if (cashSection) {
            cashSection.style.display = currentPaymentMethod === 'cash' && status === 'paid' ? 'block' : 'none';
        }
        updateSplitBreakdown(paidAmount);
    }

    function getSelectedCouponMode() {
        return document.getElementById('coupon_mode_product')?.checked ? 'PRODUCT_BUY' : 'DIRECT';
    }

    function setCouponProduct(selectedIndex) {
        cart.forEach((item, index) => {
            item.use_coupon = index === selectedIndex;
        });
    }

    function ensureCouponProductSelection() {
        let selectedIndex = -1;
        cart.forEach((item, index) => {
            if (item.use_coupon) selectedIndex = index;
        });
        if (selectedIndex < 0 && cart.length > 0) {
            selectedIndex = cart.length - 1;
        }
        setCouponProduct(selectedIndex);
    }

    function evaluateCouponDiscount(itemsTotal) {
        const select = document.getElementById('pos_coupon_select');
        const hiddenId = document.getElementById('applied_coupon_id');
        const hiddenMode = document.getElementById('applied_coupon_mode');
        const feedback = document.getElementById('pos_coupon_feedback');
        const mode = getSelectedCouponMode();
        if (hiddenMode) hiddenMode.value = mode;

        if (!select || !select.value) {
            if (hiddenId) hiddenId.value = '';
            if (feedback) {
                feedback.classList.add('d-none');
                feedback.textContent = '';
            }
            return { valid: true, discount: 0 };
        }

        const opt = select.options[select.selectedIndex];
        const couponType = (opt.dataset.type || 'FIXED').toUpperCase();
        const couponVal = parseFloat(opt.dataset.value || '0') || 0;
        const minBill = parseFloat(opt.dataset.min || '0') || 0;
        let extraTotal = 0;
        cart.forEach(item => {
            if (item.use_coupon) {
                extraTotal += calculateLineAmounts(item).billedAmount;
            }
        });
        extraTotal = Math.round(extraTotal * 100) / 100;

        let error = '';
        let discount = 0;

        if (mode === 'DIRECT') {
            const reqMin = couponType === 'FIXED' ? Math.max(minBill, couponVal) : minBill;
            if (itemsTotal + 0.0001 < reqMin) {
                error = `Bill total must be at least ₹${reqMin.toFixed(2)} for this coupon.`;
            } else {
                const raw = couponType === 'PERCENT' ? Math.round(itemsTotal * (couponVal / 100) * 100) / 100 : couponVal;
                discount = Math.min(itemsTotal, Math.max(0, raw));
            }
        } else {
            if (extraTotal <= 0) {
                error = 'Select one product to apply this coupon.';
            } else if (itemsTotal + 0.0001 < minBill) {
                error = `Overall bill total must be at least ₹${minBill.toFixed(2)}.`;
            } else if (couponType === 'FIXED' && extraTotal + 0.0001 < couponVal) {
                error = `Coupon product(s) total (₹${extraTotal.toFixed(2)}) must be at least ₹${couponVal.toFixed(2)}.`;
            } else {
                const raw = couponType === 'PERCENT' ? Math.round(extraTotal * (couponVal / 100) * 100) / 100 : couponVal;
                discount = Math.min(extraTotal, Math.max(0, raw));
            }
        }

        if (error) {
            if (hiddenId) hiddenId.value = '';
            if (feedback) {
                feedback.className = 'small mt-2 text-danger fw-semibold';
                feedback.textContent = error;
            }
            return { valid: false, discount: 0 };
        }

        if (hiddenId) hiddenId.value = select.value;
        if (feedback) {
            feedback.className = 'small mt-2 text-success fw-semibold';
            feedback.textContent = `Coupon ${opt.dataset.code || ''} applied (${mode === 'DIRECT' ? 'Direct Bill' : 'Product Buy'}): -₹${discount.toFixed(2)}`;
        }
        return { valid: true, discount: Math.round(discount * 100) / 100 };
    }

    document.getElementById('pos_coupon_select')?.addEventListener('change', function() {
        if (this.value && getSelectedCouponMode() === 'PRODUCT_BUY') {
            ensureCouponProductSelection();
        }
        renderCart();
    });

    document.querySelectorAll('input[name="coupon_mode_radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (getSelectedCouponMode() === 'PRODUCT_BUY') {
                ensureCouponProductSelection();
            }
            renderCart();
        });
    });

    function calculateCartTotals() {
        let taxableSubtotal = 0.00;
        let totalGst = 0.00;
        let totalDiscount = 0.00;
        let totalItemsCount = 0;

        cart.forEach(item => {
            const line = calculateLineAmounts(item);
            taxableSubtotal += line.taxableAmount;
            totalGst += line.gstAmount;
            totalDiscount += line.discount;
            totalItemsCount += Math.max(1, parseInt(item.qty, 10) || 1);
        });

        const itemsTotal = Math.round((taxableSubtotal + totalGst) * 100) / 100;
        const couponResult = evaluateCouponDiscount(itemsTotal);
        const couponDiscount = couponResult.discount;
        const shippingAmount = Math.max(0, parseFloat(shippingAmountInput ? shippingAmountInput.value : '0') || 0);
        const netPayable = Math.round((Math.max(0, itemsTotal - couponDiscount) + shippingAmount) * 100) / 100;

        // Update displays
        if (displayItemsCount) displayItemsCount.textContent = totalItemsCount;
        if (displayTaxableSubtotal) displayTaxableSubtotal.textContent = '₹' + taxableSubtotal.toFixed(2);
        
        if (badgeGstIncl) {
            const priceMode = gstPriceModeSelect ? gstPriceModeSelect.value : 'exclude';
            const gstType = gstTypeSelect ? gstTypeSelect.value : 'both';
            const badgePrefix = gstType === 'exempt' ? 'Exempt ₹' : (priceMode === 'include' ? 'Included ₹' : 'Added ₹');
            badgeGstIncl.textContent = badgePrefix + totalGst.toFixed(2);
        }
        if (displayCgst) displayCgst.textContent = '₹' + (totalGst / 2.0).toFixed(2);
        if (displaySgst) displaySgst.textContent = '₹' + (totalGst / 2.0).toFixed(2);

        const combinedDiscount = Math.round((totalDiscount + couponDiscount) * 100) / 100;
        if (badgeDiscount) badgeDiscount.textContent = '-₹' + combinedDiscount.toFixed(2);
        if (displaySavings) displaySavings.textContent = '₹' + combinedDiscount.toFixed(2);

        if (displayNetPayable) displayNetPayable.textContent = '₹' + netPayable.toFixed(2);

        // Update hidden JSON
        if (cartItemsJson) cartItemsJson.value = JSON.stringify(cart);

        // Update cash received change calculation
        updateCashChange(netPayable);
        updatePaymentStatusDetails(netPayable);
    }

    function updateSplitBreakdown(netPayable) {
        if (!splitCashAmount || !splitUpiAmount || !splitTotalAmount || !splitBalanceStatus) return;

        const cashAmount = Math.max(0, parseFloat(splitCashAmount.value) || 0);
        const upiAmount = Math.max(0, parseFloat(splitUpiAmount.value) || 0);
        const splitTotal = Math.round((cashAmount + upiAmount) * 100) / 100;
        const difference = Math.round((netPayable - splitTotal) * 100) / 100;
        splitTotalAmount.textContent = '₹' + splitTotal.toFixed(2);

        if (difference === 0) {
            splitBalanceStatus.textContent = 'Balanced (₹0.00)';
            splitBalanceStatus.className = 'badge rounded-pill bg-success-subtle text-success border border-success-subtle';
        } else {
            splitBalanceStatus.textContent = (difference > 0 ? 'Remaining ' : 'Over by ') + '₹' + Math.abs(difference).toFixed(2);
            splitBalanceStatus.className = 'badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        }
    }

    function balanceSplitForCash() {
        if (!splitCashAmount || !splitUpiAmount || !displayNetPayable) return;
        const netPayable = parseFloat(displayNetPayable.textContent.replace('₹', '')) || 0;
        const paidAmount = getPaidAmount(netPayable);
        const cashAmount = Math.max(0, parseFloat(splitCashAmount.value) || 0);
        splitUpiAmount.value = Math.max(0, paidAmount - cashAmount).toFixed(2);
        updateSplitBreakdown(paidAmount);
    }

    if (gstTypeSelect) {
        gstTypeSelect.addEventListener('change', () => renderCart());
    }
    if (gstPriceModeSelect) {
        gstPriceModeSelect.addEventListener('change', () => renderCart());
    }
    if (shippingAmountInput) {
        shippingAmountInput.addEventListener('input', () => renderCart());
    }
    if (paymentStatusSelect) {
        paymentStatusSelect.addEventListener('change', () => renderCart());
    }
    if (advancePaidInput) {
        advancePaidInput.addEventListener('input', () => {
            const currentNet = parseFloat(displayNetPayable.textContent.replace('₹', '')) || 0;
            updatePaymentStatusDetails(currentNet);
        });
    }

    // 6. Payment Method Selection (Cards)
    function updateOnlineClassificationVisibility() {
        const isCash = currentPaymentMethod === 'cash';
        if (onlineClassificationWrap) onlineClassificationWrap.classList.toggle('d-none', !isCash);
        if (!isCash && toggleOnline) toggleOnline.checked = false;
    }

    updateOnlineClassificationVisibility();

    document.querySelectorAll('.pay-method-btn').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.pay-method-btn').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            this.setAttribute('aria-pressed', 'true');
            document.querySelectorAll('.pay-method-btn').forEach(button => {
                if (button !== this) button.setAttribute('aria-pressed', 'false');
            });
            currentPaymentMethod = this.getAttribute('data-method');
            if (inputPaymentMethod) inputPaymentMethod.value = currentPaymentMethod;
            updateOnlineClassificationVisibility();

            if (splitPaymentSection) {
                splitPaymentSection.style.display = currentPaymentMethod === 'split' ? 'block' : 'none';
            }

            const currentNet = parseFloat(displayNetPayable.textContent.replace('₹', '')) || 0;
            updatePaymentStatusDetails(currentNet);
        });
    });

    if (splitCashAmount) {
        splitCashAmount.addEventListener('input', balanceSplitForCash);
    }
    if (splitUpiAmount) {
        splitUpiAmount.addEventListener('input', function() {
            const netPayable = parseFloat(displayNetPayable.textContent.replace('₹', '')) || 0;
            updateSplitBreakdown(getPaidAmount(netPayable));
        });
    }
    if (splitAutoBalance) {
        splitAutoBalance.addEventListener('click', balanceSplitForCash);
    }

    // 7. Cash Received & Change Calculator
    function calculateCashGiven() {
        return Math.max(0, parseFloat(cashCustomerGave ? cashCustomerGave.value : '0') || 0);
    }

    function updateCashChange(netPayable) {
        const cashGiven = calculateCashGiven();

        if (netPayable <= 0) {
            if (displayAmountDue) displayAmountDue.textContent = '₹0.00';
            if (displayChangeReturn) displayChangeReturn.textContent = '₹0.00';
            return;
        }

        const amountDue = Math.max(0, netPayable);
        if (displayAmountDue) displayAmountDue.textContent = '₹' + amountDue.toFixed(2);

        const changeReturn = Math.max(0, cashGiven - netPayable);
        if (displayChangeReturn) displayChangeReturn.textContent = '₹' + changeReturn.toFixed(2);
    }

    if (cashCustomerGave) {
        cashCustomerGave.addEventListener('input', function() {
            const currentNet = parseFloat(displayNetPayable.textContent.replace('₹', '')) || 0;
            updateCashChange(currentNet);
        });
    }

    function validatePaymentDetails() {
        const shippingRaw = shippingAmountInput ? shippingAmountInput.value.trim() : '0';
        const shippingAmount = shippingRaw === '' ? 0 : Number(shippingRaw);
        if ((shippingRaw !== '' && !/^\d+(?:\.\d{1,2})?$/.test(shippingRaw))
            || !Number.isFinite(shippingAmount) || shippingAmount < 0 || shippingAmount > 9999999999.99) {
            showPosInlineError('Enter a valid non-negative shipping amount.', shippingAmountInput);
            return false;
        }

        const paymentStatus = paymentStatusSelect ? paymentStatusSelect.value : 'paid';
        const currentNet = parseFloat(displayNetPayable.textContent.replace('₹', '')) || 0;
        const advancePaid = Math.max(0, parseFloat(advancePaidInput ? advancePaidInput.value : '0') || 0);
        if (paymentStatus === 'partial' && (advancePaid <= 0 || advancePaid >= currentNet)) {
            showPosInlineError('For Partially Paid / COD, advance paid must be greater than zero and less than the invoice total.', advancePaidInput);
            return false;
        }
        if (paymentStatus === 'unpaid' && advancePaid > 0) {
            showPosInlineError('Unpaid / COD invoices cannot have an advance payment.');
            return false;
        }
        if (currentPaymentMethod === 'cash' && paymentStatus === 'paid') {
            const cashGiven = calculateCashGiven();
            if (cashGiven > 0 && cashGiven < currentNet) {
                showPosInlineError('Customer gave less than the invoice total. Choose Partially Paid / COD and enter the advance amount.', paymentStatusSelect);
                return false;
            }
        }
        return true;
    }

    if (posBillingForm) {
        posBillingForm.addEventListener('submit', function(e) {
            if (billingPartyType.value === 'customer') {
                if (!/^[6-9][0-9]{9}$/.test(custMobile.value.trim())) {
                    e.preventDefault();
                    showPosInlineError('Enter a valid 10-digit customer mobile number.', custMobile);
                    return false;
                }
                if (!custName.value.trim()) {
                    e.preventDefault();
                    showPosInlineError('Enter the customer name.', custName);
                    return false;
                }
                if (!custAddress.value.trim()) {
                    e.preventDefault();
                    showPosInlineError('Enter the customer address.', custAddress);
                    return false;
                }
            } else if (!companyName.value.trim()) {
                e.preventDefault();
                showPosInlineError('Enter the company name.', companyName);
                return false;
            }
            if (!validatePaymentDetails()) e.preventDefault();
        });
    }

    // 8. Complete Sale Validation
    if (btnCompleteSale) {
        btnCompleteSale.addEventListener('click', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                showPosInlineError('Billing cart is empty. Scan a garment QR code or type an SKU before completing the sale.', qrInput);
                return false;
            }

            // Customer or company details are mandatory.
            if (billingPartyType.value === 'customer') {
                if (!/^[6-9][0-9]{9}$/.test(custMobile.value.trim())) {
                    e.preventDefault();
                    showPosInlineError('Enter a valid 10-digit customer mobile number.', custMobile);
                    return false;
                }
                if (!custName.value.trim()) {
                    e.preventDefault();
                    showPosInlineError('Enter the customer name.', custName);
                    return false;
                }
                if (!custAddress.value.trim()) {
                    e.preventDefault();
                    showPosInlineError('Enter the customer address.', custAddress);
                    return false;
                }
            } else {
                if (!companyName.value.trim()) {
                    e.preventDefault();
                    showPosInlineError('Enter the company name.', companyName);
                    return false;
                }
            }

            if (!validatePaymentDetails()) {
                e.preventDefault();
                return false;
            }
        });
    }

    // Initialize Empty State & Customer Channel Visibility
    updateCustomerChannelFields();
    if (posEditData) {
        document.getElementById('pos_invoice_date').value = posEditData.invoice_date;
        billingPartyType.value = posEditData.billing_party_type;
        if (customerChannelSelect) customerChannelSelect.value = posEditData.customer_channel;
        updateBillingPartyFields();
        if (custMobile) custMobile.value = posEditData.customer_mobile;
        if (custName) custName.value = posEditData.customer_name;
        if (custAddress) custAddress.value = posEditData.customer_address;
        if (companyName) companyName.value = posEditData.company_name;
        if (companyAddress) companyAddress.value = posEditData.company_address;
        if (companyPhone) companyPhone.value = posEditData.company_phone;
        if (companyEmail) companyEmail.value = posEditData.company_email;
        if (companyGstin) companyGstin.value = posEditData.company_gstin;
        if (directBillCheckbox) directBillCheckbox.checked = posEditData.is_direct_bill;
        if (gstTypeSelect) gstTypeSelect.value = posEditData.gst_type;
        if (gstPriceModeSelect) gstPriceModeSelect.value = posEditData.gst_price_mode;
        if (paymentStatusSelect) paymentStatusSelect.value = posEditData.payment_status;
        if (shippingAmountInput) shippingAmountInput.value = Number(posEditData.shipping_amount).toFixed(2);
        if (advancePaidInput) advancePaidInput.value = Number(posEditData.paid_amount).toFixed(2);
        if (toggleOnline) toggleOnline.checked = posEditData.is_online;
        const channelOption = customerChannelSelect && customerChannelSelect.options[customerChannelSelect.selectedIndex];
        const channelButton = document.getElementById('customerChannelDropdownBtn');
        if (channelOption && channelButton) channelButton.textContent = channelOption.textContent.trim();
        const paymentButton = document.querySelector('.pay-method-btn[data-method="' + posEditData.payment_method + '"]');
        if (paymentButton) paymentButton.click();
        cart = Array.isArray(posEditData.items) ? posEditData.items : [];
    }
    updateCustomerChannelFields();
    renderCart();

    let customerChannelsData = <?= json_encode(array_values($channelsList), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const channelCsrf = '<?= htmlspecialchars($csrfToken ?? \App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>';
    const canEditChannel = <?= \App\Core\Auth::can('billing', 'edit') ? 'true' : 'false' ?>;
    const canDeleteChannel = <?= \App\Core\Auth::can('billing', 'delete') ? 'true' : 'false' ?>;
    const editChannelIdInput = document.getElementById('edit_channel_id');
    const channelNameInput = document.getElementById('new_channel_name');
    const channelOrderCheck = document.getElementById('new_channel_is_order');
    const channelErrBox = document.getElementById('new_channel_error');
    const saveChannelBtn = document.getElementById('btn_save_customer_channel');
    const cancelEditChannelBtn = document.getElementById('btn_cancel_edit_channel');
    const channelFormTitle = document.getElementById('channel_form_mode_title');
    const channelTbody = document.getElementById('customer_channels_tbody');
    const channelModalEl = document.getElementById('modalAddCustomerChannel');
    const channelDropdownBtn = document.getElementById('customerChannelDropdownBtn');
    const channelDropdownMenu = document.getElementById('customerChannelDropdownMenu');

    function updateChannelDropdownButtonLabel() {
        if (!channelDropdownBtn || !customerChannelSelect) return;
        const opt = customerChannelSelect.options[customerChannelSelect.selectedIndex];
        channelDropdownBtn.textContent = opt ? opt.textContent.trim() : 'Direct Customer';
    }

    function openEditChannelInModal(ch) {
        if (!ch) return;
        if (editChannelIdInput) editChannelIdInput.value = ch.id || '';
        if (channelNameInput) {
            channelNameInput.value = ch.channel_name || '';
        }
        if (channelOrderCheck) {
            channelOrderCheck.checked = Number(ch.is_order_channel) === 1;
            channelOrderCheck.disabled = false;
        }
        if (channelFormTitle) channelFormTitle.textContent = 'Edit Customer Channel: ' + ch.channel_name;
        if (saveChannelBtn) saveChannelBtn.innerHTML = '<i class="bi bi-save me-1"></i> Update Field';
        if (cancelEditChannelBtn) cancelEditChannelBtn.classList.remove('d-none');
        if (channelErrBox) channelErrBox.classList.add('d-none');
        if (channelModalEl && window.bootstrap) {
            channelModalEl.dataset.keepEdit = '1';
            bootstrap.Modal.getOrCreateInstance(channelModalEl).show();
            setTimeout(() => { channelNameInput && channelNameInput.focus(); }, 200);
        }
    }

    function deleteChannelByRecord(ch, btnEl = null) {
        if (!ch || !ch.id) return;
        if (!confirm('Delete customer channel "' + ch.channel_name + '"?')) return;
        if (btnEl) btnEl.disabled = true;
        fetch('<?= \App\Core\Controller::url('/m/billing/channels/delete') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ csrf_token: channelCsrf, channel_id: String(ch.id || 0) }).toString()
        })
        .then(r => r.json())
        .then(data => {
            if (btnEl) btnEl.disabled = false;
            if (!data || !data.success) {
                const msg = (data && data.message) || 'Could not delete customer channel.';
                if (channelErrBox) {
                    channelErrBox.textContent = msg;
                    channelErrBox.classList.remove('d-none');
                } else {
                    showPosInlineError(msg);
                }
                return;
            }
            const nextKey = customerChannelSelect && customerChannelSelect.value === ch.channel_key ? null : customerChannelSelect.value;
            resetChannelForm();
            syncCustomerChannelDropdown(data.channels || [], nextKey);
        })
        .catch(() => {
            if (btnEl) btnEl.disabled = false;
            showPosInlineError('Network error while deleting customer channel.');
        });
    }

    function renderCustomChannelDropdownMenu() {
        if (!channelDropdownMenu) return;
        const currentKey = customerChannelSelect ? customerChannelSelect.value : '';
        const canDeleteAny = canDeleteChannel && Array.isArray(customerChannelsData) && customerChannelsData.length > 1;
        channelDropdownMenu.innerHTML = '';

        if (!Array.isArray(customerChannelsData) || customerChannelsData.length === 0) {
            channelDropdownMenu.innerHTML = '<div class="px-3 py-2 text-muted small">No channels found.</div>';
            return;
        }

        customerChannelsData.forEach(ch => {
            const isSelected = currentKey === ch.channel_key;
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between rounded-2 px-2 py-1';
            row.style.cursor = 'pointer';
            row.style.marginBottom = '3px';
            row.style.minHeight = '36px';
            if (isSelected) {
                row.style.backgroundColor = '#eff6ff';
                row.style.color = '#1d4ed8';
            }

            const labelWrap = document.createElement('div');
            labelWrap.className = 'd-flex align-items-center gap-2 flex-grow-1 text-truncate me-2';
            labelWrap.innerHTML = `
                <i class="bi ${isSelected ? 'bi-check-circle-fill text-primary' : 'bi-circle text-muted opacity-50'}" style="font-size: 0.78rem;"></i>
                <span class="fw-semibold small text-truncate">${escapeHtml(ch.channel_name)}</span>
                ${Number(ch.is_order_channel) === 1
                    ? `<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size:0.64rem; padding: 2px 6px;">Shipping &amp; Payment</span>`
                    : ''}
            `;
            labelWrap.addEventListener('click', function () {
                if (customerChannelSelect) {
                    customerChannelSelect.value = ch.channel_key;
                    updateChannelDropdownButtonLabel();
                    updateCustomerChannelFields();
                    renderCustomChannelDropdownMenu();
                    renderCustomerChannelsTable();
                }
                if (channelDropdownBtn && window.bootstrap) {
                    bootstrap.Dropdown.getOrCreateInstance(channelDropdownBtn).hide();
                }
            });

            const actions = document.createElement('div');
            actions.className = 'd-flex align-items-center gap-1 flex-shrink-0';
            if (canEditChannel && Number(ch.id) > 0) {
                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-sm btn-outline-secondary';
                editBtn.style.cssText = 'width: 28px; height: 28px; padding: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; background: #ffffff;';
                editBtn.innerHTML = '<i class="bi bi-pencil-square" style="font-size:0.78rem;"></i>';
                editBtn.title = 'Edit ' + ch.channel_name;
                editBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (channelDropdownBtn && window.bootstrap) {
                        bootstrap.Dropdown.getOrCreateInstance(channelDropdownBtn).hide();
                    }
                    openEditChannelInModal(ch);
                });
                actions.appendChild(editBtn);
            }
            if (canDeleteAny && Number(ch.id) > 0) {
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'btn btn-sm btn-outline-danger';
                delBtn.style.cssText = 'width: 28px; height: 28px; padding: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; background: #ffffff;';
                delBtn.innerHTML = '<i class="bi bi-trash3" style="font-size:0.78rem;"></i>';
                delBtn.title = 'Delete ' + ch.channel_name;
                delBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    deleteChannelByRecord(ch, delBtn);
                });
                actions.appendChild(delBtn);
            }

            row.appendChild(labelWrap);
            row.appendChild(actions);
            if (!isSelected) {
                row.addEventListener('mouseenter', () => { row.style.backgroundColor = '#f8fafc'; });
                row.addEventListener('mouseleave', () => { row.style.backgroundColor = ''; });
            }
            channelDropdownMenu.appendChild(row);
        });
    }

    function syncCustomerChannelDropdown(channels, selectKey = null) {
        if (!customerChannelSelect || !Array.isArray(channels)) return;
        customerChannelsData = channels;
        const previousVal = selectKey !== null ? selectKey : customerChannelSelect.value;
        customerChannelSelect.innerHTML = '';
        channels.forEach(ch => {
            const opt = document.createElement('option');
            opt.value = ch.channel_key;
            opt.textContent = ch.channel_name;
            opt.dataset.channelId = ch.id || 0;
            opt.dataset.orderChannel = Number(ch.is_order_channel) === 1 ? '1' : '0';
            customerChannelSelect.appendChild(opt);
        });
        if (previousVal && Array.from(customerChannelSelect.options).some(o => o.value === previousVal)) {
            customerChannelSelect.value = previousVal;
        } else if (channels.length > 0) {
            customerChannelSelect.value = channels[0].channel_key;
        }
        updateChannelDropdownButtonLabel();
        updateCustomerChannelFields();
        renderCustomChannelDropdownMenu();
        renderCustomerChannelsTable();
    }

    function resetChannelForm() {
        if (editChannelIdInput) editChannelIdInput.value = '';
        if (channelNameInput) channelNameInput.value = '';
        if (channelOrderCheck) {
            channelOrderCheck.checked = false;
            channelOrderCheck.disabled = false;
        }
        if (channelErrBox) channelErrBox.classList.add('d-none');
        if (channelFormTitle) channelFormTitle.textContent = 'Add New Customer Channel / Field';
        if (saveChannelBtn) saveChannelBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Save Field';
        if (cancelEditChannelBtn) cancelEditChannelBtn.classList.add('d-none');
    }

    function renderCustomerChannelsTable() {
        if (!channelTbody) return;
        const currentKey = customerChannelSelect ? customerChannelSelect.value : 'direct';
        const canDeleteAny = canDeleteChannel && Array.isArray(customerChannelsData) && customerChannelsData.length > 1;
        if (!Array.isArray(customerChannelsData) || customerChannelsData.length === 0) {
            channelTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted small py-3">No channels saved.</td></tr>';
            return;
        }
        channelTbody.innerHTML = '';
        customerChannelsData.forEach(ch => {
            const isSelected = currentKey === ch.channel_key;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <span class="fw-semibold text-dark">${escapeHtml(ch.channel_name)}</span>
                    ${isSelected ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size:0.65rem;">Selected</span>' : ''}
                </td>
                <td class="text-center">
                    ${Number(ch.is_order_channel) === 1
                        ? '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size:0.70rem;">Shipping &amp; Advance/Pending</span>'
                        : '<span class="badge bg-light text-muted border" style="font-size:0.70rem;">Direct Bill</span>'}
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 me-1 btn-select-channel" title="Use this channel"><i class="bi bi-check2"></i></button>
                    ${canEditChannel ? '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1 btn-edit-channel" title="Edit channel"><i class="bi bi-pencil-square"></i></button>' : ''}
                    ${canDeleteAny ? '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-channel" title="Delete channel"><i class="bi bi-trash3"></i></button>' : ''}
                </td>
            `;
            tr.querySelector('.btn-select-channel')?.addEventListener('click', function () {
                if (customerChannelSelect) {
                    customerChannelSelect.value = ch.channel_key;
                    updateChannelDropdownButtonLabel();
                    updateCustomerChannelFields();
                    renderCustomChannelDropdownMenu();
                    renderCustomerChannelsTable();
                }
                if (channelModalEl && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(channelModalEl).hide();
                }
            });
            tr.querySelector('.btn-edit-channel')?.addEventListener('click', function () {
                openEditChannelInModal(ch);
            });
            tr.querySelector('.btn-delete-channel')?.addEventListener('click', function () {
                deleteChannelByRecord(ch, this);
            });
            channelTbody.appendChild(tr);
        });
    }

    if (channelModalEl) {
        channelModalEl.addEventListener('show.bs.modal', function () {
            if (channelModalEl.dataset.keepEdit === '1') {
                delete channelModalEl.dataset.keepEdit;
            } else {
                resetChannelForm();
            }
            renderCustomerChannelsTable();
        });
    }
    if (cancelEditChannelBtn) {
        cancelEditChannelBtn.addEventListener('click', function () {
            resetChannelForm();
        });
    }

    updateChannelDropdownButtonLabel();
    renderCustomChannelDropdownMenu();

    if (saveChannelBtn && customerChannelSelect) {
        saveChannelBtn.addEventListener('click', function () {
            const editId = (editChannelIdInput ? editChannelIdInput.value : '').trim();
            const channelName = (channelNameInput ? channelNameInput.value : '').trim();
            if (!channelName) {
                if (channelErrBox) {
                    channelErrBox.textContent = 'Please enter a customer channel / field name.';
                    channelErrBox.classList.remove('d-none');
                }
                return;
            }
            if (channelErrBox) channelErrBox.classList.add('d-none');
            saveChannelBtn.disabled = true;

            const isEditMode = editId !== '' && Number(editId) > 0;
            const endpoint = isEditMode
                ? '<?= \App\Core\Controller::url('/m/billing/channels/update') ?>'
                : '<?= \App\Core\Controller::url('/m/billing/channels/add') ?>';
            const params = {
                csrf_token: channelCsrf,
                channel_name: channelName,
                is_order_channel: (channelOrderCheck && channelOrderCheck.checked) ? '1' : '0'
            };
            if (isEditMode) params.channel_id = editId;

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(params).toString()
            })
            .then(r => r.json())
            .then(data => {
                saveChannelBtn.disabled = false;
                if (!data || !data.success || !data.channel) {
                    if (channelErrBox) {
                        channelErrBox.textContent = (data && data.message) || 'Could not save customer channel.';
                        channelErrBox.classList.remove('d-none');
                    }
                    return;
                }
                resetChannelForm();
                if (channelOrderCheck) channelOrderCheck.disabled = false;
                if (Array.isArray(data.channels)) {
                    syncCustomerChannelDropdown(data.channels, data.channel.channel_key);
                } else {
                    const ch = data.channel;
                    let opt = Array.from(customerChannelSelect.options).find(o => o.value === ch.channel_key);
                    if (!opt) {
                        opt = document.createElement('option');
                        customerChannelSelect.appendChild(opt);
                    }
                    opt.value = ch.channel_key;
                    opt.textContent = ch.channel_name;
                    opt.dataset.channelId = ch.id || 0;
                    opt.dataset.orderChannel = Number(ch.is_order_channel) === 1 ? '1' : '0';
                    customerChannelSelect.value = ch.channel_key;
                    updateChannelDropdownButtonLabel();
                    updateCustomerChannelFields();
                    renderCustomChannelDropdownMenu();
                }
            })
            .catch(() => {
                saveChannelBtn.disabled = false;
                if (channelErrBox) {
                    channelErrBox.textContent = 'Network error while saving customer channel.';
                    channelErrBox.classList.remove('d-none');
                }
            });
        });
    }
});

function printReceiptArea(format) {
    if (!['a4', 'pos'].includes(format)) return;
    const printArea = document.getElementById(format === 'a4' ? 'a4_invoice_print_area' : 'thermal_receipt_print_area');
    if (!printArea) return;

    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
        showPosInlineError('Please allow pop-ups to print this receipt.');
        return;
    }

    const pageStyles = format === 'a4'
        ? `@page{size:A4 portrait;margin:10mm}
            body{font-family:Arial,sans-serif;color:#111;font-size:10pt}
            #a4_invoice_print_area{display:block!important;width:100%;max-width:190mm;margin:auto;padding:0!important;border:0!important;background:#fff!important;font-family:Arial,sans-serif!important;font-size:10pt!important}
            .a4-invoice-header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #222;padding-bottom:12px;margin-bottom:16px}
            .a4-invoice-brand{display:flex;align-items:flex-start;gap:10px;margin-bottom:6px}
            .a4-invoice-brand img{max-height:55px;max-width:110px;object-fit:contain;flex:0 0 auto}
            .a4-invoice-company-details h1{font-size:20pt;margin:0 0 3px}
            .a4-invoice-title{text-align:right}
            .a4-invoice-title h2{font-size:20pt;margin:0 0 4px}
            .a4-invoice-parties{display:flex;align-items:stretch;gap:12px;margin-bottom:14px}
            .a4-invoice-parties>.a4-invoice-customer,.a4-invoice-parties>.a4-invoice-meta{flex:1 1 0;min-width:0;margin:0}
            .a4-invoice-meta,.a4-invoice-customer{border:1px solid #bbb;padding:10px 12px}
            .a4-invoice-meta h3,.a4-invoice-customer h3{font-size:11pt;text-transform:uppercase;margin:0 0 7px}
            .a4-invoice-meta>div{display:flex;justify-content:space-between;gap:8px;white-space:nowrap;font-size:9pt}
            .a4-invoice-meta>div+div{margin-top:5px}
            .a4-invoice-meta>div span{text-align:right}
            .a4-invoice-items{width:100%;border-collapse:collapse;margin-bottom:16px}
            .a4-invoice-items th,.a4-invoice-items td{border:1px solid #888;padding:7px 6px;vertical-align:top}
            .a4-invoice-items th{background:#f1f5f9;text-align:left}
            .a4-invoice-items .number{text-align:right;white-space:nowrap}
            .a4-invoice-items small{display:block;color:#555}
            .a4-invoice-summary{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}
            .a4-invoice-summary>table{width:44%;border-collapse:collapse}
            .a4-invoice-summary th,.a4-invoice-summary td{padding:5px 8px;border-bottom:1px solid #ccc;text-align:right}
            .a4-invoice-summary th{text-align:left}
            .a4-invoice-total th,.a4-invoice-total td{font-weight:bold;font-size:12pt;border-top:2px solid #222}
            .a4-invoice-discount{font-size:9pt;color:#444}
            .a4-invoice-footer{display:flex;justify-content:space-between;align-items:flex-end;border-top:1px solid #888;margin-top:45px;padding-top:10px}
            .a4-invoice-signature{border-top:1px solid #555;min-width:55mm;text-align:center;padding-top:6px}`
        : `@page{size:80mm auto;margin:3mm}
            body{font-family:monospace;color:#111}
            #thermal_receipt_print_area{width:72mm;margin:auto;padding:0!important;border:0!important;background:#fff!important;font-family:monospace!important;font-size:10px!important}
            #thermal_receipt_print_area table{width:100%;font-size:9px!important}
            #thermal_receipt_print_area .thermal-receipt-items th,#thermal_receipt_print_area .thermal-receipt-items td{padding:3px 2px}
            #thermal_receipt_print_area .thermal-receipt-items th{line-height:1.1}
            #thermal_receipt_print_area h5{font-size:14px}`;

    printWindow.document.open();
    printWindow.document.write('<!doctype html><html><head><title>' + (format === 'a4' ? 'Tax Invoice' : 'POS Receipt') + '</title><style>body{margin:0;padding:0}*{box-sizing:border-box} .text-center{text-align:center}.text-end{text-align:right}.d-flex{display:flex;justify-content:space-between}.fw-bold{font-weight:700}.text-muted{color:#555}.text-danger{color:#b91c1c}.text-success{color:#047857}.border-bottom{border-bottom:1px solid #ccc}.pt-2{padding-top:8px}.py-2{padding-top:8px;padding-bottom:8px}.mb-0{margin-bottom:0}table{border-collapse:collapse}th,td{padding:3px 1px;vertical-align:top}hr{border:0;border-top:1px solid #bbb}' + pageStyles + '</style></head><body>' + printArea.outerHTML + '</body></html>');
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
        printWindow.onafterprint = function() {
            printWindow.close();
        };
    };
    printWindow.document.close();
}
</script>

<!-- Modal: Manage Customer Channels / Fields (View, Create, Edit, Delete) -->
<div class="modal fade" id="modalAddCustomerChannel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header py-3 border-bottom">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-diagram-3 text-primary me-1"></i> Manage Customer Channels / Sources</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="new_channel_error" class="alert alert-danger py-2 px-3 small d-none"></div>
                <div class="p-3 rounded-3 border bg-light mb-3">
                    <div class="small fw-bold text-dark mb-2" id="channel_form_mode_title">Add New Customer Channel / Field</div>
                    <input type="hidden" id="edit_channel_id" value="">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-7">
                            <label for="new_channel_name" class="form-label small fw-semibold text-secondary mb-1">Customer Type / Channel Name <span class="text-danger">*</span></label>
                            <input type="text" id="new_channel_name" class="form-control form-control-sm" maxlength="80" placeholder="e.g. Instagram Order, Phone Order, Walk-in VIP">
                        </div>
                        <div class="col-md-5 d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btn_cancel_edit_channel">Cancel Edit</button>
                            <button type="button" class="btn btn-primary btn-sm fw-semibold" id="btn_save_customer_channel">
                                <i class="bi bi-check-lg me-1"></i> Save Field
                            </button>
                        </div>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="new_channel_is_order">
                        <label class="form-check-label small fw-semibold text-dark" for="new_channel_is_order">
                            Enable Shipping, Payment Status, Advance Paid &amp; Pending fields
                        </label>
                        <div class="form-text small text-muted">Turn on for remote orders (like Website / WhatsApp). Leave off for direct walk-in bills.</div>
                    </div>
                </div>

                <div class="small fw-bold text-uppercase text-muted mb-2">Saved Customer Channels</div>
                <div class="table-responsive border rounded-3" style="max-height: 260px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0 table-sm">
                        <thead class="table-light small">
                            <tr>
                                <th>Channel Name</th>
                                <th class="text-center">Field Mode</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="customer_channels_tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 border-top">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
