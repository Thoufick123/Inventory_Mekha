<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-send-check text-primary me-2"></i>Stock Requisitions & Movement</h4>
            <p class="text-secondary small mb-0">Role-scoped 4-step requisition flow (Request → Dispatch / In-Transit → Receive / Shortage) moving double-entry stock.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (\App\Core\Auth::can('request', 'add')): ?>
            <a href="<?= \App\Core\Controller::url('/production/stock-requests/create') ?>" class="btn btn-primary shadow-sm fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> New Stock Requisition
            </a>
            <?php endif; ?>
            <a href="<?= \App\Core\Controller::url('/m/request') ?>" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-clock-history me-1"></i> Legacy Requests
            </a>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url('/production/stock-requests') ?>" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search Request No, Challan, Notes..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All Requisition Statuses --</option>
                        <option value="PENDING" <?= ($status ?? '') === 'PENDING' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="APPROVED" <?= ($status ?? '') === 'APPROVED' ? 'selected' : '' ?>>Approved (Ready to Dispatch)</option>
                        <option value="DISPATCHED" <?= ($status ?? '') === 'DISPATCHED' ? 'selected' : '' ?>>Dispatched (In-Transit)</option>
                        <option value="RECEIVED" <?= ($status ?? '') === 'RECEIVED' ? 'selected' : '' ?>>Received & Fulfilled</option>
                        <option value="SHORTAGE" <?= ($status ?? '') === 'SHORTAGE' ? 'selected' : '' ?>>Received with Shortage</option>
                        <option value="REJECTED" <?= ($status ?? '') === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                        <option value="CANCELLED" <?= ($status ?? '') === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate ?? '') ?>">
                </div>
                <div class="col-6 col-md-3 d-flex gap-1">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate ?? '') ?>">
                    <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-funnel"></i></button>
                    <?php if (!empty($search) || !empty($status) || !empty($fromDate) || !empty($toDate)): ?>
                        <a href="<?= \App\Core\Controller::url('/production/stock-requests') ?>" class="btn btn-sm btn-light border" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Requisitions Registry Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-truck me-2 text-primary"></i> Active Requisitions & Stock In-Transit</h6>
            <span class="badge bg-light text-dark border px-2 py-1"><?= count($requests) ?> Records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Request No</th>
                        <th>Status</th>
                        <th>Source Location</th>
                        <th>Destination</th>
                        <th>Requested</th>
                        <th>Dispatched</th>
                        <th>In-Transit</th>
                        <th>Received</th>
                        <th>Challan</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">No stock requisitions found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): 
                            $badgeClass = match ($req['status']) {
                                'RECEIVED'   => 'bg-success',
                                'DISPATCHED', 'IN_TRANSIT' => 'bg-info text-dark',
                                'SHORTAGE'   => 'bg-warning text-dark',
                                'APPROVED'   => 'bg-primary',
                                'REJECTED'   => 'bg-secondary',
                                'CANCELLED'  => 'bg-danger',
                                default      => 'bg-dark'
                            };
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= \App\Core\Controller::url('/production/stock-requests/view/' . $req['id']) ?>" class="fw-bold font-monospace text-decoration-none">
                                        <?= htmlspecialchars($req['request_no']) ?>
                                    </a>
                                    <small class="d-block text-muted"><?= htmlspecialchars($req['priority']) ?></small>
                                </td>
                                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($req['status']) ?></span></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($req['from_location_type']) ?></div>
                                    <small class="text-muted">Unit #<?= (int)$req['from_location_id'] ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($req['to_location_type']) ?></div>
                                    <small class="text-muted">Unit #<?= (int)$req['to_location_id'] ?></small>
                                </td>
                                <td><span class="fw-bold"><?= number_format((float)$req['requested_qty'], 2) ?></span></td>
                                <td><span class="text-primary fw-semibold"><?= number_format((float)$req['dispatched_qty'], 2) ?></span></td>
                                <td>
                                    <?php if ($req['in_transit_qty'] > 0): ?>
                                        <span class="badge bg-warning text-dark animate-pulse"><?= number_format((float)$req['in_transit_qty'], 2) ?> In-Transit</span>
                                    <?php else: ?>
                                        <span class="text-muted">0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-success fw-bold"><?= number_format((float)$req['received_qty'], 2) ?></span>
                                    <?php if ($req['shortage_qty'] > 0): ?>
                                        <small class="d-block text-danger">(-<?= number_format((float)$req['shortage_qty'], 2) ?> shortage)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($req['dispatch_challan_no'])): ?>
                                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($req['dispatch_challan_no']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= \App\Core\Controller::url('/production/stock-requests/view/' . $req['id']) ?>" class="btn btn-outline-primary" title="View Requisition Timeline & Actions">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (!empty($req['dispatch_challan_no'])): ?>
                                            <a href="<?= \App\Core\Controller::url('/production/stock-requests/print/' . $req['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Print Dispatch Challan">
                                                <i class="bi bi-printer"></i>
                                            </a>
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
