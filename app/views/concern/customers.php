<div class="container-fluid px-0">
    <!-- Top Breadcrumb (Image 1) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="text-secondary small">
            <a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a>
            <span class="mx-1">/</span>
            <span class="text-muted">Shop</span>
            <span class="mx-1">/</span>
            <span class="text-dark fw-semibold">Retail Customers</span>
        </div>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Customer Profiles & Directory (Excel & PDF)';
    $exportSubtitle = 'Download registered client contacts, lifetime sales volume, loyalty profiles & purchase histories';
    $exportUrl = \App\Core\Controller::url('/m/customers/export');
    $prefix = 'cust_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <!-- Section Title & Subheader (Image 1) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <div class="text-muted small mb-1" style="font-size: 0.76rem;">
                <i class="bi bi-shop text-primary me-1"></i> Shop / Customer Details
            </div>
            <h4 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2" style="letter-spacing: -0.01em;">
                <i class="bi bi-people-fill text-primary fs-4"></i> Customer Directory & CRM
            </h4>
        </div>
        <div>
            <?php if (\App\Core\Auth::can('customers', 'add')): ?>
            <button type="button" id="btnAddCustomer" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalAddCustomer" style="background: #4f46e5; border-color: #4f46e5;">
                <i class="bi bi-person-plus-fill"></i> + Add Customer
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 KPI Stat Cards (Image 1) -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Customers -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100" style="border: 1px solid #e2e8f0 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase small fw-bold text-secondary mb-1" style="letter-spacing: 0.05em; font-size: 0.74rem;">TOTAL CUSTOMERS</div>
                        <div class="fs-2 fw-bold text-dark font-monospace mb-1"><?= $totalCustomers ?></div>
                        <div class="small fw-semibold" style="font-size: 0.76rem; color: #059669;">
                            <span style="color: #10b981;">●</span> <?= $activeCustomers ?> Active &bull; <span class="text-muted"><?= $inactiveCustomers ?> Inactive</span>
                        </div>
                    </div>
                    <div style="width: 48px; height: 48px; background: #eff6ff; color: #2563eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Customer Revenue -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100" style="border: 1px solid #e2e8f0 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase small fw-bold text-secondary mb-1" style="letter-spacing: 0.05em; font-size: 0.74rem;">CUSTOMER REVENUE</div>
                        <div class="fs-2 fw-bold text-success font-monospace mb-1">₹<?= number_format($totalCustomerRevenue, 2) ?></div>
                        <small class="text-muted d-block" style="font-size: 0.76rem;">
                            <i class="bi bi-building me-1"></i> Cumulative Billed Sales
                        </small>
                    </div>
                    <div style="width: 48px; height: 48px; background: #ecfdf5; color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                        <i class="bi bi-currency-rupee"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Avg Customer LTV -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100" style="border: 1px solid #e2e8f0 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase small fw-bold text-secondary mb-1" style="letter-spacing: 0.05em; font-size: 0.74rem;">AVG CUSTOMER LTV</div>
                        <div class="fs-2 fw-bold text-primary font-monospace mb-1">₹<?= number_format($avgCustomerLtv, 2) ?></div>
                        <small class="text-muted d-block" style="font-size: 0.76rem;">
                            <i class="bi bi-graph-up-arrow me-1"></i> Avg Spend per Buyer
                        </small>
                    </div>
                    <div style="width: 48px; height: 48px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                        <i class="bi bi-gem"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Repeat Customer Rate -->
        <div class="col-xl-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100" style="border: 1px solid #e2e8f0 !important;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase small fw-bold text-secondary mb-1" style="letter-spacing: 0.05em; font-size: 0.74rem;">REPEAT CUSTOMER RATE</div>
                        <div class="fs-2 fw-bold font-monospace mb-1" style="color: #d97706;"><?= $repeatCustomerRate ?>%</div>
                        <small class="text-muted d-block" style="font-size: 0.76rem;">
                            <i class="bi bi-arrow-repeat me-1"></i> <?= $repeatBuyers ?> Repeat Buyers
                        </small>
                    </div>
                    <div style="width: 48px; height: 48px; background: #fef3c7; color: #d97706; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                        <i class="bi bi-stars"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar (Image 1) -->
    <div class="card border-0 shadow-sm rounded-4 p-2 px-3 bg-white mb-3" style="border: 1px solid #e2e8f0 !important;">
        <div class="row g-2 align-items-center">
            <div class="col-md-9 col-sm-8">
                <div class="input-group">
                    <span class="input-group-text bg-white border-0 text-muted ps-1"><i class="bi bi-search"></i></span>
                    <input type="text" id="crm_search_input" class="form-control border-0 shadow-none px-2 text-dark" placeholder="Search by Name, Mobile, Email, GSTIN, Address..." style="font-size: 0.9rem;">
                </div>
            </div>
            <div class="col-md-3 col-sm-4 border-start">
                <select id="crm_city_filter" class="form-select border-0 shadow-none text-muted" style="font-size: 0.88rem;">
                    <option value="" selected>All Cities</option>
                    <?php foreach ($cities as $ct): ?>
                        <option value="<?= htmlspecialchars($ct) ?>"><?= htmlspecialchars($ct) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Customer Records Table Card (Image 1) -->
    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4 overflow-hidden" style="border: 1px solid #e2e8f0 !important;">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center gap-2">
            <h6 class="fw-bold text-dark mb-0">Customer Records</h6>
            <span class="badge rounded-pill px-3 py-1 fw-semibold" id="badge_found_count" style="background: #e0f2fe; color: #0284c7; font-size: 0.78rem;">
                <?= count($customers) ?> Customer Found
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="crm_customers_table" style="font-size: 0.86rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="width: 4%;" class="ps-4">#</th>
                        <th style="width: 25%;">CUSTOMER DETAILS</th>
                        <th style="width: 20%;">CONTACT & LOCATION</th>
                        <th style="width: 9%;" class="text-center">ORDERS</th>
                        <th style="width: 14%;">TOTAL SPEND</th>
                        <th style="width: 10%;">AVG / ORDER</th>
                        <th style="width: 14%;">LAST ACTIVITY</th>
                        <th style="width: 8%;" class="text-end pe-4">ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="crm_table_body">
                    <?php if (empty($customers)): ?>
                        <tr id="empty_row"><td colspan="8" class="text-center py-5 text-muted">No customer records found.</td></tr>
                    <?php else: ?>
                        <?php $rowNum = 1; foreach ($customers as $c): 
                            $spend = (float)($c['total_spend'] ?? 0);
                            $orders = (int)($c['orders_count'] ?? 0);
                            $avgOrder = $orders > 0 ? ($spend / $orders) : 0.00;
                            $firstLetter = strtoupper(substr(trim($c['name']), 0, 1) ?: 'C');
                        ?>
                            <tr class="crm-customer-row" 
                                data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>"
                                data-phone="<?= htmlspecialchars($c['phone'] ?: '') ?>"
                                data-email="<?= strtolower(htmlspecialchars($c['email'] ?: '')) ?>"
                                data-gstin="<?= strtolower(htmlspecialchars($c['gstin'] ?: '')) ?>"
                                data-city="<?= strtolower(htmlspecialchars($c['city'] ?: '')) ?>"
                                data-address="<?= strtolower(htmlspecialchars($c['address'] ?: '')) ?>">
                                <td class="ps-4 text-muted fw-bold"><?= $rowNum++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 36px; height: 36px; background: #e0f2fe; color: #0369a1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0;">
                                            <?= htmlspecialchars($firstLetter) ?>
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block text-capitalize"><?= htmlspecialchars($c['name']) ?></strong>
                                            <small class="text-muted font-monospace">ID: #<?= (int)$c['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small">
                                        <i class="bi bi-telephone text-primary me-1"></i> <?= htmlspecialchars($c['phone'] ?: '-') ?>
                                    </div>
                                    <small class="text-muted d-block text-truncate" style="max-width: 220px;">
                                        <i class="bi bi-geo-alt text-muted me-1"></i> <?= htmlspecialchars($c['city'] ?: ($c['address'] ?: 'Local Store')) ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1 font-monospace" style="border-radius: 6px; font-size: 0.82rem;">
                                        <?= $orders ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark font-monospace">₹<?= number_format($spend, 2) ?></div>
                                    <small class="text-muted font-monospace"><?= (int)($c['total_pcs'] ?? 0) ?> pcs</small>
                                </td>
                                <td>
                                    <span class="font-monospace text-dark fw-semibold">₹<?= number_format($avgOrder, 2) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($c['last_order_date'])): ?>
                                        <div class="small fw-semibold text-dark"><?= date('d M Y', strtotime($c['last_order_date'])) ?></div>
                                        <?php if (!empty($c['last_invoice_no'])): ?>
                                            <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;">
                                                <i class="bi bi-receipt me-1"></i> <?= htmlspecialchars($c['last_invoice_no']) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">No orders yet</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 btn-view-profile" 
                                                data-id="<?= (int)$c['id'] ?>" style="font-size: 0.78rem;">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <?php if (\App\Core\Auth::can('customers', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1 btn-edit-customer"
                                                data-id="<?= (int)$c['id'] ?>"
                                                data-name="<?= htmlspecialchars($c['name']) ?>"
                                                data-phone="<?= htmlspecialchars($c['phone'] ?: '') ?>"
                                                data-email="<?= htmlspecialchars($c['email'] ?: '') ?>"
                                                data-city="<?= htmlspecialchars($c['city'] ?: '') ?>"
                                                data-address="<?= htmlspecialchars($c['address'] ?: '') ?>"
                                                data-gstin="<?= htmlspecialchars($c['gstin'] ?: '') ?>"
                                                data-status="<?= htmlspecialchars($c['status'] ?? 'active') ?>"
                                                style="font-size: 0.78rem;">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('customers', 'delete')): ?>
                                        <form action="<?= \App\Core\Controller::url('/m/customers/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete customer <?= htmlspecialchars(addslashes($c['name'])) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1" title="Delete Customer" style="font-size: 0.78rem;">
                                                <i class="bi bi-trash"></i>
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

    <!-- Footer Tag (Image 1) -->
    <div class="d-flex justify-content-between align-items-center text-muted small py-2 px-1 mb-3" style="font-size: 0.76rem;">
        <div>&copy; <?= date('Y') ?> GRM Inventory Management. All rights reserved.</div>
        <div>v1.0</div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD CUSTOMER                                      -->
<!-- ======================================================== -->
<div class="modal fade" id="modalAddCustomer" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/customers/store') ?>" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="concern_id" value="<?= (int)($selectedCid ?: ($currentConcern['id'] ?? 1)) ?>">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus text-primary me-2"></i> Register New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">CUSTOMER / COMPANY NAME *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Mekha Retail Store" style="border-radius: 8px;">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">PHONE NUMBER</label>
                        <input type="tel" name="phone" class="form-control" maxlength="10" placeholder="10-digit mobile" style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">GSTIN (OPTIONAL)</label>
                        <input type="text" name="gstin" class="form-control text-uppercase" placeholder="33AAAAA0000A1Z5" style="border-radius: 8px;">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">EMAIL ADDRESS</label>
                        <input type="email" name="email" class="form-control" placeholder="client@domain.com" style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">CITY / DISTRICT</label>
                        <input type="text" name="city" class="form-control" placeholder="e.g. Tiruppur" style="border-radius: 8px;">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-secondary">BILLING / DELIVERY ADDRESS</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Street, landmark, pincode..." style="border-radius: 8px;"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" style="background: #4f46e5;">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT CUSTOMER                                     -->
<!-- ======================================================== -->
<div class="modal fade" id="modalEditCustomer" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= \App\Core\Controller::url('/m/customers/update') ?>" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_cust_id">
            <input type="hidden" name="concern_id" value="<?= (int)($selectedCid ?: ($currentConcern['id'] ?? 1)) ?>">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">CUSTOMER NAME *</label>
                    <input type="text" name="name" id="edit_cust_name" class="form-control" required style="border-radius: 8px;">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">PHONE NUMBER</label>
                        <input type="tel" name="phone" id="edit_cust_phone" class="form-control" maxlength="10" style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">GSTIN</label>
                        <input type="text" name="gstin" id="edit_cust_gstin" class="form-control text-uppercase" style="border-radius: 8px;">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">EMAIL ADDRESS</label>
                        <input type="email" name="email" id="edit_cust_email" class="form-control" style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">CITY</label>
                        <input type="text" name="city" id="edit_cust_city" class="form-control" style="border-radius: 8px;">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">ADDRESS</label>
                    <textarea name="address" id="edit_cust_address" class="form-control" rows="2" style="border-radius: 8px;"></textarea>
                </div>
                <div>
                    <label class="form-label small fw-bold text-secondary">ACCOUNT STATUS</label>
                    <select name="status" id="edit_cust_status" class="form-select" style="border-radius: 8px;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" style="background: #4f46e5;">Update Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW CUSTOMER 360 PROFILE & ORDER HISTORY         -->
<!-- ======================================================== -->
<div class="modal fade" id="modalViewCustomerProfile" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div id="view_avatar" style="width: 44px; height: 44px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700;">
                        C
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="view_cust_name">Customer Details</h5>
                        <small class="text-muted font-monospace" id="view_cust_code">ID: #0</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Mini Stats Row -->
                <div class="row g-2 mb-3 text-center">
                    <div class="col-4">
                        <div class="p-2 border rounded-3 bg-light">
                            <small class="text-muted d-block" style="font-size: 0.74rem;">TOTAL ORDERS</small>
                            <span class="fw-bold fs-6 font-monospace text-dark" id="view_stat_orders">0</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded-3 bg-light">
                            <small class="text-muted d-block" style="font-size: 0.74rem;">LIFETIME SPEND</small>
                            <span class="fw-bold fs-6 font-monospace text-success" id="view_stat_spend">₹0.00</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded-3 bg-light">
                            <small class="text-muted d-block" style="font-size: 0.74rem;">PIECES BOUGHT</small>
                            <span class="fw-bold fs-6 font-monospace text-primary" id="view_stat_pcs">0 pcs</span>
                        </div>
                    </div>
                </div>

                <!-- Contact Info Cards -->
                <div class="p-3 border rounded-3 bg-white mb-3" style="font-size: 0.84rem;">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div><span class="text-muted">Phone:</span> <strong id="view_info_phone" class="text-dark">-</strong></div>
                            <div><span class="text-muted">Email:</span> <span id="view_info_email" class="text-dark">-</span></div>
                        </div>
                        <div class="col-md-6">
                            <div><span class="text-muted">GSTIN:</span> <span id="view_info_gstin" class="font-monospace text-dark">-</span></div>
                            <div><span class="text-muted">Location:</span> <span id="view_info_location" class="text-dark">-</span></div>
                        </div>
                    </div>
                </div>

                <!-- Invoices / Order History Table -->
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-clock-history me-1 text-primary"></i> Purchase History & Invoices</h6>
                <div class="table-responsive border rounded-3" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="view_invoices_body">
                            <tr><td colspan="5" class="text-center py-3 text-muted">Loading purchase history...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- INTERACTIVE JAVASCRIPT: FILTER, SEARCH, EXPORT & MODALS  -->
<!-- ======================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('crm_search_input');
    const cityFilter = document.getElementById('crm_city_filter');
    const rows = document.querySelectorAll('.crm-customer-row');
    const badgeFound = document.getElementById('badge_found_count');
    const emptyRow = document.getElementById('empty_row');

    // 1. Live Client-Side Filtering
    function filterTable() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const city = cityFilter ? cityFilter.value.toLowerCase().trim() : '';
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const phone = row.getAttribute('data-phone') || '';
            const email = row.getAttribute('data-email') || '';
            const gstin = row.getAttribute('data-gstin') || '';
            const rowCity = row.getAttribute('data-city') || '';
            const address = row.getAttribute('data-address') || '';

            const matchQuery = !query || name.includes(query) || phone.includes(query) || email.includes(query) || gstin.includes(query) || address.includes(query);
            const matchCity = !city || rowCity === city;

            if (matchQuery && matchCity) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (badgeFound) {
            badgeFound.textContent = visibleCount + (visibleCount === 1 ? ' Customer Found' : ' Customers Found');
        }

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (cityFilter) cityFilter.addEventListener('change', filterTable);

    // 2. Export Format & Period Dynamic Controls
    const exportFormatSelect = document.getElementById('export_format');
    const exportPeriodSelect = document.getElementById('export_period');
    const singleDateBox = document.getElementById('single_date_container');
    const customDateBox = document.getElementById('custom_date_container');
    const btnExport = document.getElementById('btn_export_download');
    const btnExportIcon = document.getElementById('btn_export_icon');
    const btnExportLabel = document.getElementById('btn_export_label');
    const exportIconBox = document.getElementById('export_icon_box');
    const exportHeaderIcon = document.getElementById('export_header_icon');

    // Handle Period changes (show single date or custom range picker)
    if (exportPeriodSelect) {
        exportPeriodSelect.addEventListener('change', function() {
            const p = this.value;
            if (p === 'single_day') {
                if (singleDateBox) {
                    singleDateBox.classList.remove('d-none');
                    singleDateBox.classList.add('d-flex');
                }
                if (customDateBox) {
                    customDateBox.classList.add('d-none');
                    customDateBox.classList.remove('d-flex');
                }
            } else if (p === 'custom') {
                if (customDateBox) {
                    customDateBox.classList.remove('d-none');
                    customDateBox.classList.add('d-flex');
                }
                if (singleDateBox) {
                    singleDateBox.classList.add('d-none');
                    singleDateBox.classList.remove('d-flex');
                }
            } else {
                if (singleDateBox) {
                    singleDateBox.classList.add('d-none');
                    singleDateBox.classList.remove('d-flex');
                }
                if (customDateBox) {
                    customDateBox.classList.add('d-none');
                    customDateBox.classList.remove('d-flex');
                }
            }
        });
    }

    // Handle Format changes (Excel, PDF, CSV)
    if (exportFormatSelect) {
        exportFormatSelect.addEventListener('change', function() {
            const f = this.value;
            if (f === 'excel') {
                btnExport.style.background = '#16a34a';
                btnExportIcon.className = 'bi bi-file-earmark-excel';
                btnExportLabel.textContent = 'Export Excel';
                if (exportIconBox) {
                    exportIconBox.style.background = '#ecfdf5';
                    exportIconBox.style.color = '#16a34a';
                    exportIconBox.style.borderColor = '#bbf7d0';
                }
                if (exportHeaderIcon) exportHeaderIcon.className = 'bi bi-file-earmark-excel';
            } else if (f === 'pdf') {
                btnExport.style.background = '#dc2626';
                btnExportIcon.className = 'bi bi-file-earmark-pdf';
                btnExportLabel.textContent = 'Export PDF';
                if (exportIconBox) {
                    exportIconBox.style.background = '#fef2f2';
                    exportIconBox.style.color = '#dc2626';
                    exportIconBox.style.borderColor = '#fecaca';
                }
                if (exportHeaderIcon) exportHeaderIcon.className = 'bi bi-file-earmark-pdf';
            } else {
                btnExport.style.background = '#4f46e5';
                btnExportIcon.className = 'bi bi-file-earmark-spreadsheet';
                btnExportLabel.textContent = 'Export CSV';
                if (exportIconBox) {
                    exportIconBox.style.background = '#eff6ff';
                    exportIconBox.style.color = '#3b82f6';
                    exportIconBox.style.borderColor = '#dbeafe';
                }
                if (exportHeaderIcon) exportHeaderIcon.className = 'bi bi-file-earmark-arrow-down';
            }
        });
    }

    // Export Download Execution
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            const format = document.getElementById('export_format').value;
            const period = document.getElementById('export_period').value;
            const scope = document.getElementById('export_data_scope').value;
            const city = cityFilter ? cityFilter.value : '';
            const singleDate = document.getElementById('export_single_date') ? document.getElementById('export_single_date').value : '';
            const fromDate = document.getElementById('export_from_date') ? document.getElementById('export_from_date').value : '';
            const toDate = document.getElementById('export_to_date') ? document.getElementById('export_to_date').value : '';

            const exportUrl = '<?= \App\Core\Controller::url('/m/customers/export') ?>' + 
                              '?format=' + encodeURIComponent(format) + 
                              '&period=' + encodeURIComponent(period) + 
                              '&scope=' + encodeURIComponent(scope) + 
                              '&city=' + encodeURIComponent(city) + 
                              '&single_date=' + encodeURIComponent(singleDate) + 
                              '&from_date=' + encodeURIComponent(fromDate) + 
                              '&to_date=' + encodeURIComponent(toDate);
            
            if (format === 'pdf') {
                window.open(exportUrl, '_blank');
            } else {
                window.location.href = exportUrl;
            }
        });
    }

    // 2.5 Add Customer Modal Trigger
    const btnAddCustomer = document.getElementById('btnAddCustomer');
    const addModalEl = document.getElementById('modalAddCustomer');
    if (btnAddCustomer && addModalEl) {
        btnAddCustomer.addEventListener('click', function(e) {
            if (window.bootstrap) {
                const addModal = bootstrap.Modal.getOrCreateInstance(addModalEl);
                addModal.show();
            }
        });
    }

    // 3. Edit Customer Modal Population
    const editModalEl = document.getElementById('modalEditCustomer');

    document.querySelectorAll('.btn-edit-customer').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_cust_id').value = this.getAttribute('data-id');
            document.getElementById('edit_cust_name').value = this.getAttribute('data-name');
            document.getElementById('edit_cust_phone').value = this.getAttribute('data-phone');
            document.getElementById('edit_cust_email').value = this.getAttribute('data-email');
            document.getElementById('edit_cust_city').value = this.getAttribute('data-city');
            document.getElementById('edit_cust_address').value = this.getAttribute('data-address');
            document.getElementById('edit_cust_gstin').value = this.getAttribute('data-gstin');
            document.getElementById('edit_cust_status').value = this.getAttribute('data-status') || 'active';

            if (editModalEl && window.bootstrap) {
                const editModal = bootstrap.Modal.getOrCreateInstance(editModalEl);
                editModal.show();
            }
        });
    });

    // 4. View Customer 360 CRM Modal
    const viewModalEl = document.getElementById('modalViewCustomerProfile');

    document.querySelectorAll('.btn-view-profile').forEach(btn => {
        btn.addEventListener('click', function() {
            const custId = this.getAttribute('data-id');
            const invoicesTbody = document.getElementById('view_invoices_body');
            invoicesTbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading profile...</td></tr>';

            if (viewModalEl && window.bootstrap) {
                const viewModal = bootstrap.Modal.getOrCreateInstance(viewModalEl);
                viewModal.show();
            }

            fetch('<?= \App\Core\Controller::url('/api/customers/details?id=') ?>' + encodeURIComponent(custId))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.customer) {
                        const c = data.customer;
                        document.getElementById('view_cust_name').textContent = c.name;
                        document.getElementById('view_cust_code').textContent = 'ID: #' + c.id + ' • ' + (c.code || 'CUST');
                        document.getElementById('view_avatar').textContent = (c.name.trim().charAt(0) || 'C').toUpperCase();

                        document.getElementById('view_stat_orders').textContent = data.orders_count || 0;
                        document.getElementById('view_stat_spend').textContent = '₹' + parseFloat(data.total_spend || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                        document.getElementById('view_stat_pcs').textContent = (data.total_pcs || 0) + ' pcs';

                        document.getElementById('view_info_phone').textContent = c.phone || 'N/A';
                        document.getElementById('view_info_email').textContent = c.email || 'N/A';
                        document.getElementById('view_info_gstin').textContent = c.gstin || 'Unregistered';
                        document.getElementById('view_info_location').textContent = (c.address ? c.address + ', ' : '') + (c.city || 'Local');

                        if (!data.invoices || data.invoices.length === 0) {
                            invoicesTbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No sales invoices recorded yet.</td></tr>';
                        } else {
                            invoicesTbody.innerHTML = '';
                            data.invoices.forEach(inv => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td><span class="badge bg-dark font-monospace">${escapeHtml(inv.invoice_no)}</span></td>
                                    <td class="text-muted font-monospace">${escapeHtml(inv.invoice_date)}</td>
                                    <td><span class="badge bg-light text-dark border">${parseInt(inv.items_count || 0)} items (${parseInt(inv.pcs_count || 0)} pcs)</span></td>
                                    <td><span class="badge bg-primary bg-opacity-10 text-primary text-uppercase">${escapeHtml(inv.payment_method)}</span></td>
                                    <td class="text-end fw-bold font-monospace text-success">₹${parseFloat(inv.net_amount || 0).toFixed(2)}</td>
                                `;
                                invoicesTbody.appendChild(tr);
                            });
                        }
                    } else {
                        invoicesTbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Failed to load customer profile.</td></tr>';
                    }
                })
                .catch(err => {
                    invoicesTbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Network error loading profile.</td></tr>';
                });
        });
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }
});
</script>
