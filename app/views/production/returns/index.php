<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-arrow-repeat text-warning me-2"></i>Returns & Exchange (RMA)</h4>
            <p class="text-secondary small mb-0">Reversible Return Merchandise Authorization with real exchanges, multi-line double-entry ledger & cancellation recovery.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (\App\Core\Auth::can('return_exchange', 'add')): ?>
            <a href="<?= \App\Core\Controller::url('/m/return_exchange') ?>" class="btn btn-warning shadow-sm fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> New Return / Exchange
            </a>
            <?php endif; ?>
            <a href="<?= \App\Core\Controller::url('/m/return_exchange#returnTransactions') ?>" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-clock-history me-1"></i> Return History
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
                        <th>Products / SKU Codes</th>
                        <th>Settlement</th>
                        <th>Net Balance</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">No RMA return/exchange records found matching criteria.</td></tr>
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
                                <td class="small">
                                    <?php if (!empty($r['product_codes'])): ?>
                                        <span class="font-monospace text-muted"><?= nl2br(htmlspecialchars(str_replace(' | ', "\n", $r['product_codes']), ENT_QUOTES, 'UTF-8')) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
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
                                        <?php if ($r['status'] !== 'CANCELLED' && \App\Core\Auth::can('return_exchange', 'edit')): ?>
                                            <?php if (!empty($r['sales_invoice_id']) && $r['status'] === 'COMPLETED'): ?>
                                                <a href="<?= \App\Core\Controller::url('/m/return_exchange?edit_rma=' . (int)$r['id']) ?>" class="btn btn-outline-warning" title="Edit Full Return / Exchange">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-warning" title="Edit RMA"
                                                    data-bs-toggle="modal" data-bs-target="#modalEditRma"
                                                    data-rma-id="<?= (int)$r['id'] ?>"
                                                    data-return-no="<?= htmlspecialchars($r['return_no'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-return-date="<?= htmlspecialchars(substr((string)$r['return_date'], 0, 10), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-reason="<?= htmlspecialchars((string)($r['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($r['status'] !== 'CANCELLED' && \App\Core\Auth::can('return_exchange', 'delete')): ?>
                                            <button type="button" class="btn btn-outline-danger" title="Cancel and reverse RMA"
                                                data-bs-toggle="modal" data-bs-target="#modalCancelRma"
                                                data-rma-id="<?= (int)$r['id'] ?>"
                                                data-return-no="<?= htmlspecialchars($r['return_no'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-x-circle"></i>
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

    <?php if (\App\Core\Auth::can('return_exchange', 'edit')): ?>
    <div class="modal fade" id="modalEditRma" tabindex="-1" aria-labelledby="modalEditRmaTitle" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="<?= \App\Core\Controller::url('/production/returns/update') ?>" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="id" id="edit_rma_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEditRmaTitle">Edit Return / Exchange</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Return No</label>
                        <input type="text" id="edit_rma_return_no" class="form-control font-monospace" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_rma_return_date" class="form-label">Return Date</label>
                        <input type="date" name="return_date" id="edit_rma_return_date" class="form-control" required>
                    </div>
                    <div>
                        <label for="edit_rma_reason" class="form-label">Reason / Notes</label>
                        <textarea name="reason" id="edit_rma_reason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (\App\Core\Auth::can('return_exchange', 'delete')): ?>
    <div class="modal fade" id="modalCancelRma" tabindex="-1" aria-labelledby="modalCancelRmaTitle" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="<?= \App\Core\Controller::url('/production/returns/cancel') ?>" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="id" id="cancel_rma_id">
                <input type="hidden" name="return_to" value="production_returns">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger" id="modalCancelRmaTitle">Cancel &amp; Reverse RMA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">
                        This reverses all stock movements. The RMA remains in the audit history as cancelled.
                        <span class="fw-semibold" id="cancel_rma_return_no"></span>
                    </p>
                    <label for="cancel_rma_reason" class="form-label">Cancellation Reason</label>
                    <textarea name="cancel_reason" id="cancel_rma_reason" rows="3" class="form-control" required></textarea>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep Active</button>
                    <button type="submit" class="btn btn-danger">Confirm Reverse</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    document.getElementById('modalEditRma')?.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('edit_rma_id').value = button.dataset.rmaId || '';
        document.getElementById('edit_rma_return_no').value = button.dataset.returnNo || '';
        document.getElementById('edit_rma_return_date').value = button.dataset.returnDate || '';
        document.getElementById('edit_rma_reason').value = button.dataset.reason || '';
    });

    document.getElementById('modalCancelRma')?.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('cancel_rma_id').value = button.dataset.rmaId || '';
        document.getElementById('cancel_rma_return_no').textContent = button.dataset.returnNo || '';
        document.getElementById('cancel_rma_reason').value = '';
    });
    </script>
</div>
