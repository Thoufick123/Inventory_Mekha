<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-arrow-repeat text-warning me-2"></i>Returns & Exchange (RMA)</h4>
            <p class="text-secondary small mb-0">Reversible Return Merchandise Authorization with real exchanges, multi-line double-entry ledger & cancellation recovery.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (\App\Core\Auth::can('return_exchange', 'add')): ?>
            <a href="<?= \App\Core\Controller::url('/production/returns/create') ?>" class="btn btn-warning shadow-sm fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> New Return / Exchange
            </a>
            <?php endif; ?>
            <a href="<?= \App\Core\Controller::url('/m/return_exchange') ?>" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-clock-history me-1"></i> Legacy Log
            </a>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= \App\Core\Controller::url('/production/returns') ?>" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search Return No, Customer, Reason..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">-- All Types --</option>
                        <option value="RETURN" <?= ($type ?? '') === 'RETURN' ? 'selected' : '' ?>>Return Only</option>
                        <option value="EXCHANGE" <?= ($type ?? '') === 'EXCHANGE' ? 'selected' : '' ?>>Exchange</option>
                        <option value="CREDIT_NOTE" <?= ($type ?? '') === 'CREDIT_NOTE' ? 'selected' : '' ?>>Credit Note</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All Statuses --</option>
                        <option value="RECEIVED" <?= ($status ?? '') === 'RECEIVED' ? 'selected' : '' ?>>Received</option>
                        <option value="INSPECTED" <?= ($status ?? '') === 'INSPECTED' ? 'selected' : '' ?>>Inspected</option>
                        <option value="COMPLETED" <?= ($status ?? '') === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
                        <option value="CANCELLED" <?= ($status ?? '') === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate ?? '') ?>">
                </div>
                <div class="col-6 col-md-2 d-flex gap-1">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate ?? '') ?>">
                    <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-funnel"></i></button>
                    <?php if (!empty($search) || !empty($status) || !empty($type) || !empty($fromDate) || !empty($toDate)): ?>
                        <a href="<?= \App\Core\Controller::url('/production/returns') ?>" class="btn btn-sm btn-light border" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- RMA Master Records Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-card-checklist me-2 text-primary"></i> RMA Transaction Registry</h6>
            <span class="badge bg-light text-dark border px-2 py-1"><?= count($returns) ?> Records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Return No</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Customer</th>
                        <th>Items Count</th>
                        <th>Settlement</th>
                        <th>Net Balance</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No RMA return/exchange records found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $r): 
                            $badgeClass = match ($r['status']) {
                                'COMPLETED' => 'bg-success',
                                'CANCELLED' => 'bg-danger',
                                'INSPECTED' => 'bg-info text-dark',
                                default     => 'bg-secondary'
                            };
                            $typeBadge = match ($r['type']) {
                                'EXCHANGE'    => 'bg-primary',
                                'CREDIT_NOTE' => 'bg-dark',
                                default       => 'bg-warning text-dark'
                            };
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= \App\Core\Controller::url('/production/returns/view/' . $r['id']) ?>" class="fw-bold text-decoration-none font-monospace">
                                        <?= htmlspecialchars($r['return_no']) ?>
                                    </a>
                                </td>
                                <td><span class="badge <?= $typeBadge ?>"><?= htmlspecialchars($r['type']) ?></span></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($r['customer_name'] ?: 'Retail Walk-in') ?></div>
                                    <?php if (!empty($r['customer_phone'])): ?>
                                        <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($r['customer_phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= (int)$r['item_count'] ?> Line(s)</span>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= htmlspecialchars($r['settlement_mode'] ?: 'Credit Note') ?></div>
                                    <?php if (!empty($r['settlement_reference'])): ?>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($r['settlement_reference']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['refund_amount'] > 0): ?>
                                        <span class="text-danger fw-bold">Refund ₹<?= number_format((float)$r['refund_amount'], 2) ?></span>
                                    <?php elseif ($r['extra_charge'] > 0): ?>
                                        <span class="text-success fw-bold">Collect ₹<?= number_format((float)$r['extra_charge'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">₹0.00 (Even)</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($r['return_date']) ?></small></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= \App\Core\Controller::url('/production/returns/view/' . $r['id']) ?>" class="btn btn-outline-primary" title="View RMA Dossier">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= \App\Core\Controller::url('/production/returns/print/' . $r['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Print RMA Receipt">
                                            <i class="bi bi-printer"></i>
                                        </a>
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
