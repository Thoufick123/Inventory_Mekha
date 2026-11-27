<div class="container-fluid px-0">
    <!-- Breadcrumb -->
    <div class="d-flex align-items-center gap-1 text-secondary small mb-2">
        <a href="<?= \App\Core\Controller::url('/') ?>" class="text-decoration-none text-muted">Home</a>
        <span class="text-muted">/</span>
        <span class="text-muted">Cash &amp; Cash Flow</span>
        <span class="text-muted">/</span>
        <span class="text-dark fw-semibold">Operational Expenses</span>
    </div>

    <!-- Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
        <div>
            <div class="mb-2">
                <span class="badge rounded-pill px-3 py-1 text-uppercase fw-bold" style="background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; font-size: 0.72rem; letter-spacing: 0.04em;">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Warehouse Operations
                </span>
            </div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; background: #e0e7ff; color: #4338ca; border-radius: 8px; font-size: 1.15rem;">
                    <i class="bi bi-wallet2"></i>
                </span>
                Warehouse Operational Expenses & Petty Cash
            </h4>
            <p class="text-secondary small mb-0">Manage carton boxes, freight transport, forklift maintenance, utility bills, and petty cash vouchers</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Record Expense Button -->
            <?php if (\App\Core\Auth::can('expense', 'add')): ?>
            <button class="btn text-white fw-bold shadow-sm d-flex align-items-center gap-2 px-3 py-2" style="background: #4f46e5; border-radius: 8px; font-size: 0.88rem;" data-bs-toggle="modal" data-bs-target="#modalAddExpense">
                <i class="bi bi-plus-circle"></i> Record Warehouse Expense
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Executive Export Intelligence Toolbar (Excel, PDF, Single Day, Monthly, 3 Months, 6 Months) -->
    <?php if (\App\Core\Auth::can('expense', 'export')): ?>
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4" style="border: 1px solid #e2e8f0 !important;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 44px; height: 44px; background: #ecfdf5; color: #16a34a; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; border: 1px solid #bbf7d0;">
                    <i class="bi bi-file-earmark-excel"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Export Enterprise Reports (Excel & PDF)</h6>
                    <small class="text-muted">Generate consolidated tenant performance, inventory valuation, purchases & revenue as per date period</small>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Period Selector -->
                <div class="d-flex align-items-center gap-1">
                    <label class="small text-muted mb-0 fw-semibold" style="font-size: 0.76rem;">Period:</label>
                    <select id="expense_export_period" class="form-select form-select-sm" style="width: 175px; border-radius: 8px; font-size: 0.85rem;">
                        <option value="all" selected>All Time</option>
                        <option value="single_day">Single Day</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="3_months">Particular 3 Months</option>
                        <option value="6_months">Six Months</option>
                        <option value="custom">Custom Date Range</option>
                    </select>
                </div>

                <!-- Single Date Picker (Shown for single_day) -->
                <div id="expense_single_date_box" class="d-none align-items-center gap-1">
                    <input type="date" id="expense_single_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 130px; border-radius: 8px; font-size: 0.82rem;">
                </div>

                <!-- Custom Range Pickers (Shown for custom) -->
                <div id="expense_custom_date_box" class="d-none align-items-center gap-1">
                    <input type="date" id="expense_from_date" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>" style="width: 125px; border-radius: 8px; font-size: 0.82rem;">
                    <span class="text-muted small">to</span>
                    <input type="date" id="expense_to_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 125px; border-radius: 8px; font-size: 0.82rem;">
                </div>

                <!-- Export Dropdown Button -->
                <div class="dropdown">
                    <button type="button" class="btn btn-success btn-sm px-3 fw-bold d-flex align-items-center gap-2 shadow-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px; height: 35px; background: #16a34a; border: none;">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm py-1" style="border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.88rem; min-width: 170px;">
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-success" href="javascript:void(0)" onclick="runExpenseToolbarExport('excel')">
                                <i class="bi bi-file-earmark-excel fs-6"></i> Export Excel
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-danger" href="javascript:void(0)" onclick="runExpenseToolbarExport('pdf')">
                                <i class="bi bi-file-earmark-pdf fs-6"></i> Export PDF
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center gap-2 fw-semibold text-secondary" href="javascript:void(0)" onclick="runExpenseToolbarExport('csv')">
                                <i class="bi bi-file-earmark-arrow-down fs-6"></i> CSV
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    </div>
    <?php endif; ?>

    <!-- 4 KPI Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Warehouse Outflow -->
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border: 1px solid #bbf7d0 !important; border-left: 4px solid #10b981 !important; border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase fw-bold small mb-1" style="color: #059669; font-size: 0.74rem; letter-spacing: 0.04em;">
                            Total Warehouse Outflow
                        </div>
                        <h3 class="fw-bold text-dark mb-1 font-monospace" style="letter-spacing: -0.02em;">
                            ₹<?= number_format($totalOutflow, 2) ?>
                        </h3>
                        <small class="text-muted d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                            <i class="bi bi-journal-text text-secondary"></i> <?= count($expenses) ?> Expense Vouchers
                        </small>
                    </div>
                    <div style="width: 42px; height: 42px; background: #d1fae5; color: #059669; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: bold; flex-shrink: 0;">
                        ₹
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Cash & Petty Disbursed -->
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%); border: 1px solid #bfdbfe !important; border-left: 4px solid #3b82f6 !important; border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase fw-bold small mb-1" style="color: #2563eb; font-size: 0.74rem; letter-spacing: 0.04em;">
                            Cash & Petty Disbursed
                        </div>
                        <h3 class="fw-bold text-dark mb-1 font-monospace" style="letter-spacing: -0.02em;">
                            ₹<?= number_format($cashDisbursed, 2) ?>
                        </h3>
                        <small class="text-muted d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                            <i class="bi bi-wallet2 text-secondary"></i> Physical Cash Drawer
                        </small>
                    </div>
                    <div style="width: 42px; height: 42px; background: #dbeafe; color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: UPI & Bank Transfers -->
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: linear-gradient(135deg, #ecfeff 0%, #ffffff 100%); border: 1px solid #a5f3fc !important; border-left: 4px solid #06b6d4 !important; border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase fw-bold small mb-1" style="color: #0891b2; font-size: 0.74rem; letter-spacing: 0.04em;">
                            UPI & Bank Transfers
                        </div>
                        <h3 class="fw-bold text-dark mb-1 font-monospace" style="letter-spacing: -0.02em;">
                            ₹<?= number_format($bankDisbursed, 2) ?>
                        </h3>
                        <small class="text-muted d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                            <i class="bi bi-bank text-secondary"></i> Direct Vendor NEFT/UPI
                        </small>
                    </div>
                    <div style="width: 42px; height: 42px; background: #cffafe; color: #0891b2; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                        <i class="bi bi-credit-card-2-front"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: Pending Payables -->
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%); border: 1px solid #fde68a !important; border-left: 4px solid #f59e0b !important; border-radius: 12px;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-uppercase fw-bold small mb-1" style="color: #d97706; font-size: 0.74rem; letter-spacing: 0.04em;">
                            Pending Payables
                        </div>
                        <h3 class="fw-bold text-dark mb-1 font-monospace" style="letter-spacing: -0.02em;">
                            ₹<?= number_format($pendingPayables, 2) ?>
                        </h3>
                        <small class="text-muted d-flex align-items-center gap-1" style="font-size: 0.78rem;">
                            <i class="bi bi-hourglass-split text-secondary"></i> Unsettled Invoices
                        </small>
                    </div>
                    <div style="width: 42px; height: 42px; background: #fef3c7; color: #d97706; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar Card -->
    <div class="card border-0 shadow-sm p-3 bg-white mb-4" style="border: 1px solid #e2e8f0 !important; border-radius: 12px;">
        <form method="GET" action="<?= \App\Core\Controller::url('/m/expense') ?>" class="row g-2 align-items-end">
            <!-- Warehouse / Concern Filter -->
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.78rem;">
                    <i class="bi bi-building me-1"></i> Warehouse
                </label>
                <select name="concern_id" class="form-select form-select-sm" style="border-radius: 8px; font-size: 0.84rem;">
                    <?php if (empty($selectedCid)): ?>
                        <option value="" <?= empty($filterConcernId) ? 'selected' : '' ?>>All Warehouses / Concerns</option>
                    <?php endif; ?>
                    <?php foreach ($allConcerns as $con): ?>
                        <option value="<?= $con['id'] ?>" <?= ($filterConcernId == $con['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- From Date -->
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.78rem;">
                    From Date
                </label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate) ?>" style="border-radius: 8px; font-size: 0.84rem;">
            </div>

            <!-- To Date -->
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.78rem;">
                    To Date
                </label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate) ?>" style="border-radius: 8px; font-size: 0.84rem;">
            </div>

            <!-- Category -->
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.78rem;">
                    Category
                </label>
                <select name="category" class="form-select form-select-sm" style="border-radius: 8px; font-size: 0.84rem;">
                    <option value="" <?= empty($category) ? 'selected' : '' ?>>All Categories</option>
                    <?php foreach ($categoriesList as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Payment Mode -->
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.78rem;">
                    Payment Mode
                </label>
                <select name="payment_mode" class="form-select form-select-sm" style="border-radius: 8px; font-size: 0.84rem;">
                    <option value="" <?= empty($payMode) ? 'selected' : '' ?>>All Modes</option>
                    <?php foreach ($paymentModes as $pm): ?>
                        <option value="<?= htmlspecialchars($pm) ?>" <?= ($payMode === $pm) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pm) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search payee / bill -->
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="d-flex gap-1">
                    <div class="flex-grow-1">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search payee/bill..." value="<?= htmlspecialchars($search) ?>" style="border-radius: 8px; font-size: 0.84rem;">
                    </div>
                    <button type="submit" class="btn btn-sm text-white px-3 fw-bold" style="background: #4f46e5; border-radius: 8px;" title="Filter Expenses">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if (!empty($search) || !empty($category) || !empty($payMode) || !empty($filterConcernId)): ?>
                        <a href="<?= \App\Core\Controller::url('/m/expense') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;" title="Reset Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Warehouse Expenses Register Table Card -->
    <div class="card border-0 shadow-sm bg-white overflow-hidden mb-4" style="border: 1px solid #e2e8f0 !important; border-radius: 12px;">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #e0e7ff; color: #4338ca; border-radius: 6px; font-size: 0.95rem;">
                    <i class="bi bi-card-checklist"></i>
                </span>
                <div>
                    <h6 class="mb-0 fw-bold text-dark">Warehouse Expenses Register</h6>
                    <small class="text-secondary" style="font-size: 0.78rem;">
                        Showing <?= count($expenses) ?> records from <?= date('d M Y', strtotime($fromDate)) ?> to <?= date('d M Y', strtotime($toDate)) ?>
                    </small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill px-3 py-2 fw-bold font-monospace" style="background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; font-size: 0.82rem;">
                    Total: ₹<?= number_format($totalOutflow, 2) ?>
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <tr class="text-uppercase text-secondary fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                        <th class="ps-4">Voucher #</th>
                        <th>Warehouse</th>
                        <th>Category</th>
                        <th>Paid To / Vendor</th>
                        <th>Payment Mode</th>
                        <th>Status</th>
                        <th class="text-end">Amount (INR)</th>
                        <th>Date</th>
                        <th class="text-center pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="mb-3 text-muted" style="opacity: 0.45;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto">
                                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                                        <path d="m3.3 7 8.7 5 8.7-5"></path>
                                        <path d="M12 22V12"></path>
                                    </svg>
                                </div>
                                <p class="text-secondary small mb-0 fw-medium">No warehouse expenses found for the selected period.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td class="ps-4 font-monospace fw-bold text-dark">
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($e['expense_no']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($e['concern_name'] ?? 'WH-01') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($e['concern_code'] ?? 'WH-01') ?></small>
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-2.5 py-1 text-primary bg-primary bg-opacity-10 border border-primary border-opacity-25" style="font-size: 0.74rem;">
                                        <?= htmlspecialchars($e['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-dark fw-semibold"><?= htmlspecialchars($e['paid_to'] ?: 'Counter Payee') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border">
                                        <?= htmlspecialchars($e['payment_mode']) ?>
                                    </span>
                                    <?php if (!empty($e['account_name'])): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-wallet2 me-1 text-primary"></i><?= htmlspecialchars($e['account_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (strtolower($e['status'] ?? 'paid') === 'pending'): ?>
                                        <span class="badge rounded-pill px-2.5 py-1 bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size: 0.74rem;">
                                            <i class="bi bi-hourglass me-1"></i> Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill px-2.5 py-1 bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.74rem;">
                                            <i class="bi bi-check-circle me-1"></i> Paid
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace fw-bold fs-6 <?= (strtolower($e['status'] ?? 'paid') === 'pending') ? 'text-warning' : 'text-danger' ?>">
                                    ₹<?= number_format($e['amount'], 2) ?>
                                </td>
                                <td class="text-muted font-monospace" style="font-size: 0.82rem;">
                                    <?= date('d M Y', strtotime($e['expense_date'])) ?>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info px-2 py-1" onclick='showVoucherDetails(<?= json_encode($e, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Voucher Details" style="border-radius: 6px;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (\App\Core\Auth::can('expense', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1" onclick='openEditExpenseModal(<?= json_encode($e, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Expense Voucher" style="border-radius: 6px;">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('expense', 'delete')): ?>
                                        <form action="<?= \App\Core\Controller::url('/m/expense/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete expense voucher #<?= htmlspecialchars($e['expense_no']) ?>? This will reverse any cash register entries.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1" title="Delete Voucher" style="border-radius: 6px;">
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
</div>

<?php if (\App\Core\Auth::can('expense', 'add')): ?>
<!-- RECORD WAREHOUSE EXPENSE MODAL -->
<div class="modal fade" id="modalAddExpense" tabindex="-1" aria-labelledby="modalAddExpenseLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="<?= \App\Core\Controller::url('/m/expense/store') ?>" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div class="modal-header border-bottom px-4 py-3" style="background: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 38px; height: 38px; background: #4f46e5; color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalAddExpenseLabel">Record Warehouse Expense Voucher</h5>
                        <small class="text-muted">Issue a petty cash or bank disbursal voucher for warehouse operations</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-3">
                    <!-- Warehouse / Concern -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            Warehouse / Concern <span class="text-danger">*</span>
                        </label>
                        <select name="concern_id" class="form-select form-select-sm" required style="border-radius: 8px;">
                            <?php foreach ($allConcerns as $con): ?>
                                <option value="<?= $con['id'] ?>" <?= ($selectedCid == $con['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($con['name']) ?> (<?= htmlspecialchars($con['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Expense Date -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            Expense Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="expense_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required style="border-radius: 8px;">
                    </div>

                    <!-- Category with Live Filtration & Instant Add -->
                    <div class="col-md-6 position-relative" id="cat_combobox_wrapper">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            Category <span class="text-danger">*</span>
                        </label>
                        
                        <!-- Searchable & Filterable Combobox Input Group -->
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white text-muted border-end-0" style="border-radius: 8px 0 0 8px;">
                                <i class="bi bi-tag"></i>
                            </span>
                            <input type="text" 
                                   id="expense_category_input" 
                                   name="category" 
                                   class="form-control form-control-sm border-start-0 border-end-0" 
                                   placeholder="Type to filter or add category..." 
                                   autocomplete="off" 
                                   required 
                                   onfocus="openCategoryDropdown()" 
                                   oninput="filterCategoryDropdown(this.value)"
                                   onkeydown="handleCategoryKeydown(event)"
                                   style="font-size: 0.84rem;">
                            <button type="button" 
                                    class="btn btn-light border border-start-0 text-secondary" 
                                    id="btn_toggle_cat_dropdown" 
                                    onclick="toggleCategoryDropdown()" 
                                    tabindex="-1"
                                    style="border-radius: 0 8px 8px 0; background: #f8fafc;" 
                                    title="Show all categories">
                                <i class="bi bi-chevron-down" id="cat_chevron_icon" style="font-size: 0.75rem;"></i>
                            </button>
                        </div>

                        <!-- Live Filter & Suggestions Dropdown Box -->
                        <div id="category_dropdown_menu" 
                             class="position-absolute w-100 bg-white shadow-lg rounded-3 border d-none" 
                             style="top: 100%; left: 0; z-index: 1060; margin-top: 3px; max-height: 240px; overflow-y: auto; border-color: #cbd5e1 !important;">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>

                    <!-- Paid To / Vendor -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            Paid To / Vendor / Payee
                        </label>
                        <input type="text" name="paid_to" class="form-control form-control-sm" placeholder="e.g. Sri Balaji Packaging Corp" style="border-radius: 8px;">
                    </div>

                    <!-- Amount -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            Amount (₹) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light fw-bold">₹</span>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" placeholder="0.00" required style="border-radius: 0 8px 8px 0; font-weight: 600;">
                        </div>
                    </div>

                    <!-- Payment Mode -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            Payment Mode <span class="text-danger">*</span>
                        </label>
                        <select name="payment_mode" class="form-select form-select-sm" required style="border-radius: 8px;">
                            <?php foreach ($paymentModes as $pm): ?>
                                <option value="<?= htmlspecialchars($pm) ?>"><?= htmlspecialchars($pm) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Voucher Status -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" class="form-select form-select-sm" required style="border-radius: 8px;">
                            <option value="Paid" selected>Paid</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>

                    <!-- Disbursed From Cash/Bank Account (Cash Flow Interconnection) -->
                    <div class="col-12">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            <i class="bi bi-wallet2 text-primary me-1"></i> Disbursed From Cash/Bank Account (Cash Flow)
                        </label>
                        <select name="account_id" id="add_expense_account_id" class="form-select form-select-sm" style="border-radius: 8px;">
                            <option value="">-- Auto-detect (Cash Drawer / Bank by Payment Mode) --</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>" data-concern="<?= $acc['concern_id'] ?>">
                                    <?= htmlspecialchars($acc['name']) ?> (<?= ucfirst($acc['account_type']) ?> - Current Bal: ₹<?= number_format((float)$acc['balance'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" style="font-size: 0.74rem;">
                            <i class="bi bi-info-circle me-1"></i>Interconnected with Cash Flow. Debits will post immediately to the Cash &amp; Cash Flow ledger.
                        </small>
                    </div>

                    <!-- Description / Notes -->
                    <div class="col-12">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                            Description / Bill Reference Notes
                        </label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="e.g. Bill #PKG-4029 approved by warehouse manager" style="border-radius: 8px;"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top px-4 py-3 bg-light">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                <button type="submit" class="btn btn-sm text-white px-4 fw-bold shadow-sm" style="background: #4f46e5; border-radius: 8px;">
                    <i class="bi bi-check2-circle me-1"></i> Save Expense Voucher
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- VIEW VOUCHER MODAL -->
<div class="modal fade" id="modalViewVoucher" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt fs-4 text-primary"></i>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="view_voucher_title">Warehouse Expense Voucher</h6>
                        <small class="text-muted" id="view_voucher_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small">Amount Disbursed:</span>
                        <span class="fw-bold fs-4 text-danger font-monospace" id="view_amount"></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary small">Status:</span>
                        <span class="badge bg-success" id="view_status">Paid</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Warehouse:</span>
                        <strong class="text-dark" id="view_warehouse"></strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Category:</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary" id="view_category"></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Particulars / Items:</span>
                        <strong class="text-dark" id="view_particulars">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Paid To / Payee:</span>
                        <strong class="text-dark" id="view_paid_to"></strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Payment Mode:</span>
                        <span class="badge bg-light text-dark border" id="view_pay_mode"></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Disbursed From Account:</span>
                        <strong class="text-dark" id="view_account_name">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Date:</span>
                        <span class="font-monospace text-dark" id="view_date"></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted">Notes / Memo:</span>
                        <span class="text-muted" id="view_description"></span>
                    </li>
                </ul>
            </div>
            <div class="modal-footer border-top px-4 py-2 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Voucher
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (\App\Core\Auth::can('expense', 'edit')): ?>
<!-- EDIT EXPENSE MODAL -->
<div class="modal fade" id="modalEditExpense" tabindex="-1" aria-labelledby="modalEditExpenseLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="<?= \App\Core\Controller::url('/m/expense/update') ?>" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_expense_id" value="">

            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 32px; height: 32px; background: #e0e7ff; color: #4338ca; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-pencil-square"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Edit Expense Voucher</h6>
                        <small class="text-muted" id="edit_expense_voucher_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Expense Date *</label>
                        <input type="date" name="expense_date" id="edit_expense_date" class="form-control form-control-sm" required style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Amount (INR) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="edit_expense_amount" class="form-control form-control-sm font-monospace fw-bold" required style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Category *</label>
                        <input type="text" name="category" id="edit_expense_category" class="form-control form-control-sm" required style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Status *</label>
                        <select name="status" id="edit_expense_status" class="form-select form-select-sm" required style="border-radius: 8px;">
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Paid To / Payee</label>
                        <input type="text" name="paid_to" id="edit_expense_paid_to" class="form-control form-control-sm" placeholder="e.g. Counter Payee" style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Particulars / Items</label>
                        <input type="text" name="item_particulars" id="edit_expense_particulars" class="form-control form-control-sm" style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Payment Mode *</label>
                        <select name="payment_mode" id="edit_expense_payment_mode" class="form-select form-select-sm" required style="border-radius: 8px;">
                            <?php foreach ($paymentModes as $pm): ?>
                                <option value="<?= htmlspecialchars($pm) ?>"><?= htmlspecialchars($pm) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Disbursed From Account</label>
                        <select name="account_id" id="edit_expense_account_id" class="form-select form-select-sm" style="border-radius: 8px;">
                            <option value="">-- Auto-detect (Cash Drawer / Bank) --</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= ucfirst($acc['account_type']) ?> - Bal: ₹<?= number_format((float)$acc['balance'], 2) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Description / Bill Notes</label>
                        <textarea name="description" id="edit_expense_description" class="form-control form-control-sm" rows="2" style="border-radius: 8px;"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top px-4 py-3 bg-light">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary px-4 fw-bold shadow-sm" style="border-radius: 8px;">
                    <i class="bi bi-save me-1"></i> Update Voucher
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
let expenseCategories = <?= json_encode($categoriesList) ?>;

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeJs(text) {
    if (!text) return '';
    return text.toString()
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"');
}

function highlightMatch(text, term) {
    if (!term) return escapeHtml(text);
    const escapedText = escapeHtml(text);
    const escapedTerm = escapeHtml(term);
    const regex = new RegExp('(' + escapedTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return escapedText.replace(regex, '<span class="fw-bold text-primary text-decoration-underline">$1</span>');
}

function renderCategoryDropdown(filterText = '') {
    const menu = document.getElementById('category_dropdown_menu');
    if (!menu) return;

    const term = (filterText || '').trim().toLowerCase();
    
    // Find matching categories
    const matches = expenseCategories.filter(cat => cat.toLowerCase().includes(term));
    const exactMatch = expenseCategories.find(cat => cat.toLowerCase() === term);

    let html = '';

    if (expenseCategories.length === 0 && !term) {
        html += `<div class="p-3 text-center text-muted small">
            <i class="bi bi-info-circle me-1 text-primary"></i> No categories created yet.<br>
            <span class="text-secondary">Type a category name above to create one.</span>
        </div>`;
    } else {
        if (term && matches.length > 0) {
            html += `<div class="px-3 py-1 bg-light border-bottom text-uppercase fw-bold text-secondary d-flex justify-content-between align-items-center" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                <span>Matching Categories (${matches.length})</span>
                <span class="text-muted fw-normal" style="font-size: 0.65rem;">Click to select</span>
            </div>`;
        } else if (!term && expenseCategories.length > 0) {
            html += `<div class="px-3 py-1 bg-light border-bottom text-uppercase fw-bold text-secondary d-flex justify-content-between align-items-center" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                <span>Available Categories (${expenseCategories.length})</span>
                <span class="text-muted fw-normal" style="font-size: 0.65rem;">Click to select</span>
            </div>`;
        }

        if (matches.length > 0) {
            matches.forEach(cat => {
                const isExact = (cat.toLowerCase() === term);
                html += `<div class="cat-item px-3 py-2 d-flex justify-content-between align-items-center border-bottom" 
                              style="cursor: pointer; transition: background 0.15s; font-size: 0.84rem; background: white;"
                              onmouseover="this.style.background='#f1f5f9'" 
                              onmouseout="this.style.background='white'"
                              onclick="selectCategoryItem('${escapeJs(cat)}')">
                    <span class="fw-medium text-dark d-flex align-items-center">
                        <i class="bi bi-tag me-2 text-primary"></i>
                        <span>${highlightMatch(cat, term)}</span>
                    </span>
                    <span class="badge ${isExact ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary'} small fw-normal" style="font-size: 0.72rem;">
                        <i class="bi bi-check-circle me-1"></i>Already exists
                    </span>
                </div>`;
            });
        } else if (term) {
            html += `<div class="px-3 py-2 text-muted small bg-white border-bottom">
                <i class="bi bi-search me-1 text-secondary"></i> No existing category matches "<strong>${escapeHtml(filterText.trim())}</strong>"
            </div>`;
        }

        // If user typed something and it does not exactly match an existing category, offer "+ Add as new category"
        if (term && !exactMatch) {
            html += `<div class="p-2 bg-light border-top">
                <button type="button" class="btn btn-sm btn-success w-100 d-flex align-items-center justify-content-center gap-1 fw-bold shadow-sm" 
                        onclick="saveCategoryFromInput('${escapeJs(filterText.trim())}')" style="font-size: 0.8rem; border-radius: 6px;">
                    <i class="bi bi-plus-circle me-1"></i>+ Add "${escapeHtml(filterText.trim())}" as New Category
                </button>
            </div>`;
        }
    }

    menu.innerHTML = html;
}

function openCategoryDropdown() {
    const menu = document.getElementById('category_dropdown_menu');
    const input = document.getElementById('expense_category_input');
    const chevron = document.getElementById('cat_chevron_icon');
    if (!menu) return;
    renderCategoryDropdown(input ? input.value : '');
    menu.classList.remove('d-none');
    if (chevron) { chevron.classList.remove('bi-chevron-down'); chevron.classList.add('bi-chevron-up'); }
}

function closeCategoryDropdown() {
    const menu = document.getElementById('category_dropdown_menu');
    const chevron = document.getElementById('cat_chevron_icon');
    if (menu) menu.classList.add('d-none');
    if (chevron) { chevron.classList.remove('bi-chevron-up'); chevron.classList.add('bi-chevron-down'); }
}

function toggleCategoryDropdown() {
    const menu = document.getElementById('category_dropdown_menu');
    if (!menu) return;
    if (menu.classList.contains('d-none')) {
        openCategoryDropdown();
        const input = document.getElementById('expense_category_input');
        if (input) input.focus();
    } else {
        closeCategoryDropdown();
    }
}

function filterCategoryDropdown(val) {
    openCategoryDropdown();
    renderCategoryDropdown(val);
}

function selectCategoryItem(name) {
    const input = document.getElementById('expense_category_input');
    if (input) {
        input.value = name;
        input.classList.remove('is-invalid');
    }
    closeCategoryDropdown();
}

function triggerAddCategoryFocus() {
    const input = document.getElementById('expense_category_input');
    if (input) {
        input.value = '';
        input.focus();
        openCategoryDropdown();
    }
}

function handleCategoryKeydown(e) {
    if (e.key === 'Enter') {
        const input = document.getElementById('expense_category_input');
        const term = input ? input.value.trim() : '';
        if (!term) return;

        // Check if exact match exists
        const exact = expenseCategories.find(c => c.toLowerCase() === term.toLowerCase());
        if (exact) {
            e.preventDefault();
            selectCategoryItem(exact);
        } else {
            // New category, save it
            e.preventDefault();
            saveCategoryFromInput(term);
        }
    } else if (e.key === 'Escape') {
        closeCategoryDropdown();
    }
}

function saveCategoryFromInput(name) {
    if (!name) return;
    const csrfToken = document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '';
    const formData = new URLSearchParams();
    formData.append('csrf_token', csrfToken);
    formData.append('name', name);

    fetch('<?= \App\Core\Controller::url('/m/expense/category/store') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const newCat = data.name;
            if (!expenseCategories.some(c => c.toLowerCase() === newCat.toLowerCase())) {
                expenseCategories.push(newCat);
                expenseCategories.sort();
            }
            selectCategoryItem(newCat);

            // Also update top filter dropdown if present
            const filterSelect = document.querySelector('select[name="category"]');
            if (filterSelect) {
                let exists = false;
                for (let opt of filterSelect.options) {
                    if (opt.value.toLowerCase() === newCat.toLowerCase()) { exists = true; break; }
                }
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = newCat;
                    opt.textContent = newCat;
                    filterSelect.appendChild(opt);
                }
            }
        }
    })
    .catch(console.error);
}

// Close category suggestions when clicking outside
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('cat_combobox_wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        closeCategoryDropdown();
    }
});

function showVoucherDetails(voucher) {
    if (!voucher) return;
    const setTxt = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.innerText = val;
    };

    setTxt('view_voucher_title', voucher.category || 'Operational Expense');
    setTxt('view_voucher_no', voucher.expense_no || '');
    setTxt('view_amount', '₹' + parseFloat(voucher.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));
    setTxt('view_status', voucher.status || 'Paid');
    setTxt('view_warehouse', (voucher.concern_name || 'Warehouse (WH-01)') + (voucher.concern_code ? ' (' + voucher.concern_code + ')' : ''));
    setTxt('view_category', voucher.category || '-');
    setTxt('view_particulars', voucher.item_particulars || voucher.description || '-');
    setTxt('view_paid_to', voucher.paid_to || 'Counter Payee');
    setTxt('view_pay_mode', voucher.payment_mode || 'Cash');
    setTxt('view_account_name', voucher.account_name || 'Main Cash Drawer');
    setTxt('view_date', voucher.expense_date || '');
    setTxt('view_description', voucher.description || '-');

    const modalEl = document.getElementById('modalViewVoucher');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function openEditExpenseModal(exp) {
    if (!exp) return;
    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val;
    };
    const setTxt = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.innerText = val;
    };

    setVal('edit_expense_id', exp.id || '');
    setTxt('edit_expense_voucher_no', exp.expense_no || '');
    setVal('edit_expense_date', exp.expense_date || '');
    setVal('edit_expense_amount', parseFloat(exp.amount || 0).toFixed(2));
    setVal('edit_expense_category', exp.category || '');
    setVal('edit_expense_status', exp.status === 'Pending' ? 'Pending' : 'Paid');
    setVal('edit_expense_paid_to', exp.paid_to || '');
    setVal('edit_expense_particulars', exp.item_particulars || '');
    setVal('edit_expense_payment_mode', exp.payment_mode || 'Cash');
    setVal('edit_expense_account_id', exp.account_id || '');
    setVal('edit_expense_description', exp.description || '');

    const modalEl = document.getElementById('modalEditExpense');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const periodSelect = document.getElementById('expense_export_period');
    const singleBox = document.getElementById('expense_single_date_box');
    const customBox = document.getElementById('expense_custom_date_box');

    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            const val = this.value;
            if (val === 'single_day') {
                if (singleBox) { singleBox.classList.remove('d-none'); singleBox.classList.add('d-flex'); }
                if (customBox) { customBox.classList.add('d-none'); customBox.classList.remove('d-flex'); }
            } else if (val === 'custom') {
                if (customBox) { customBox.classList.remove('d-none'); customBox.classList.add('d-flex'); }
                if (singleBox) { singleBox.classList.add('d-none'); singleBox.classList.remove('d-flex'); }
            } else {
                if (singleBox) { singleBox.classList.add('d-none'); singleBox.classList.remove('d-flex'); }
                if (customBox) { customBox.classList.add('d-none'); customBox.classList.remove('d-flex'); }
            }
        });
    }
});

function runExpenseToolbarExport(format) {
    const period = document.getElementById('expense_export_period') ? document.getElementById('expense_export_period').value : 'all';
    const singleDate = document.getElementById('expense_single_date') ? document.getElementById('expense_single_date').value : '';
    const fromDate = document.getElementById('expense_from_date') ? document.getElementById('expense_from_date').value : '';
    const toDate = document.getElementById('expense_to_date') ? document.getElementById('expense_to_date').value : '';
    const concernId = '<?= urlencode($filterConcernId ?? '') ?>';
    const category = '<?= urlencode($category ?? '') ?>';
    const payMode = '<?= urlencode($payMode ?? '') ?>';
    const search = '<?= urlencode($search ?? '') ?>';

    const url = '<?= \App\Core\Controller::url('/m/expense/export') ?>' +
                '?format=' + encodeURIComponent(format) +
                '&period=' + encodeURIComponent(period) +
                '&single_date=' + encodeURIComponent(singleDate) +
                '&from_date=' + encodeURIComponent(fromDate) +
                '&to_date=' + encodeURIComponent(toDate) +
                '&concern_id=' + concernId +
                '&category=' + category +
                '&payment_mode=' + payMode +
                '&search=' + search;

    if (format === 'pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}

function quickExpenseExport(format, period) {
    const concernId = '<?= urlencode($filterConcernId ?? '') ?>';
    const category = '<?= urlencode($category ?? '') ?>';
    const payMode = '<?= urlencode($payMode ?? '') ?>';
    const search = '<?= urlencode($search ?? '') ?>';

    const url = '<?= \App\Core\Controller::url('/m/expense/export') ?>' +
                '?format=' + encodeURIComponent(format) +
                '&period=' + encodeURIComponent(period) +
                '&concern_id=' + concernId +
                '&category=' + category +
                '&payment_mode=' + payMode +
                '&search=' + search;

    if (format === 'pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}
</script>
