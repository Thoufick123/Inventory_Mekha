<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= \App\Core\Controller::url('/production/returns') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0 text-dark">RMA <?= htmlspecialchars($return['return_no']) ?></h4>
                <small class="text-muted">Recorded on <?= htmlspecialchars($return['return_date']) ?> by <?= htmlspecialchars($return['user_name'] ?? 'System') ?></small>
            </div>
            <span class="badge <?= match($return['status']) { 'COMPLETED' => 'bg-success', 'CANCELLED' => 'bg-danger', default => 'bg-info text-dark' } ?> fs-6">
                <?= htmlspecialchars($return['status']) ?>
            </span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= \App\Core\Controller::url('/production/returns/print/' . $return['id']) ?>" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-printer me-1"></i> Print Voucher
            </a>
            <?php if ($return['status'] !== 'CANCELLED' && \App\Core\Auth::can('return_exchange', 'delete')): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelRma">
                    <i class="bi bi-x-circle me-1"></i> Cancel / Reverse RMA
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-person me-1 text-primary"></i> Customer Details</div>
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($return['customer_name'] ?: 'Retail Walk-in') ?></div>
                <div class="small text-muted"><?= htmlspecialchars($return['customer_phone'] ?: 'No phone registered') ?></div>
                <div class="small text-secondary mt-1"><?= htmlspecialchars($return['customer_address'] ?? '') ?></div>
                <?php if (!empty($return['invoice_no'])): ?>
                    <div class="mt-2 pt-2 border-top small">
                        <span class="text-muted">Linked Invoice:</span>
                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($return['invoice_no']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-cash-stack me-1 text-success"></i> Settlement & Payment</div>
                <div class="d-flex justify-content-between mb-1 small">
                    <span class="text-secondary">Settlement Mode:</span>
                    <span class="fw-semibold text-dark"><?= htmlspecialchars($return['settlement_mode'] ?: 'Credit Note') ?></span>
                </div>
                <?php if (!empty($return['settlement_reference'])): ?>
                    <div class="d-flex justify-content-between mb-1 small">
                        <span class="text-secondary">Reference No:</span>
                        <span class="font-monospace text-dark"><?= htmlspecialchars($return['settlement_reference']) ?></span>
                    </div>
                <?php endif; ?>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small fw-bold text-muted">Settlement Balance:</span>
                    <?php if ($return['refund_amount'] > 0): ?>
                        <span class="fw-bold text-danger fs-6">Refunded: ₹<?= number_format((float)$return['refund_amount'], 2) ?></span>
                    <?php elseif ($return['extra_charge'] > 0): ?>
                        <span class="fw-bold text-success fs-6">Collected: ₹<?= number_format((float)$return['extra_charge'], 2) ?></span>
                    <?php else: ?>
                        <span class="fw-bold text-muted fs-6">₹0.00 (Even)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-info-circle me-1 text-info"></i> Reason & Notes</div>
                <div class="small text-dark mb-2"><?= nl2br(htmlspecialchars($return['reason'] ?: 'No reason specified')) ?></div>
                <?php if (!empty($return['cancel_reason'])): ?>
                    <div class="alert alert-danger py-1 px-2 mb-0 small">
                        <strong>Cancellation Reason:</strong> <?= htmlspecialchars($return['cancel_reason']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Items Tables -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-warning"></i>RMA Item Details (Double-Entry Stock Audit)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Direction</th>
                        <th>Variant Style</th>
                        <th>Condition</th>
                        <th>Action & Stock Effect</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $inItems = array_filter($items, fn($i) => $i['direction'] === 'IN');
                    $outItems = array_filter($items, fn($i) => $i['direction'] === 'OUT');
                    ?>
                    <?php foreach ($inItems as $item): ?>
                        <tr>
                            <td><span class="badge bg-danger">RETURN (IN)</span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['variant_name']) ?></div>
                                <small class="badge bg-light text-dark border font-monospace">SKU: <?= htmlspecialchars($item['sku_code']) ?></small>
                                <small class="text-muted">Size: <?= htmlspecialchars($item['size'] ?? 'Standard') ?></small>
                                <?php if (!empty($item['scanned_code_display'])): ?>
                                    <small class="d-block text-primary font-monospace text-break">Scanned codes: <?= htmlspecialchars($item['scanned_code_display']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['condition_state'] ?? 'Good') ?></span></td>
                            <td>
                                <?php if ($item['restock_action'] === 'RESTOCK'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-box-arrow-in-down me-1"></i> Restocked to Live Store</span>
                                <?php elseif ($item['restock_action'] === 'REWORK'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-tools me-1"></i> Sent to Rework</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-octagon me-1"></i> Quarantined / Scrapped</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold fs-6 text-danger">+<?= number_format((float)$item['qty'], 2) ?></td>
                            <td class="text-end">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                            <td class="text-end fw-bold">₹<?= number_format((float)$item['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($outItems as $item): ?>
                        <tr class="table-success-subtle">
                            <td><span class="badge bg-success">EXCHANGE (OUT)</span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['variant_name']) ?></div>
                                <small class="badge bg-light text-dark border font-monospace">SKU: <?= htmlspecialchars($item['sku_code']) ?></small>
                                <small class="text-muted">Size: <?= htmlspecialchars($item['size'] ?? 'Standard') ?></small>
                                <?php if (!empty($item['scanned_code_display'])): ?>
                                    <small class="d-block text-primary font-monospace text-break">Scanned codes: <?= htmlspecialchars($item['scanned_code_display']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border">Brand New</span></td>
                            <td><span class="badge bg-info-subtle text-info-emphasis border border-info-subtle"><i class="bi bi-box-arrow-up-right me-1"></i> Delivered to Customer</span></td>
                            <td class="text-center fw-bold fs-6 text-success">-<?= number_format((float)$item['qty'], 2) ?></td>
                            <td class="text-end">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                            <td class="text-end fw-bold">₹<?= number_format((float)$item['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Cancel RMA Modal -->
<div class="modal fade" id="modalCancelRma" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= \App\Core\Controller::url('/production/returns/cancel') ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= $return['id'] ?>">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Cancel & Reverse RMA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-secondary small mb-3">
                    Cancelling this RMA reverses the recorded stock movements, including returned, rework, quarantined, and exchange items. The RMA remains in the audit history as cancelled.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Cancellation Reason</label>
                    <textarea name="cancel_reason" rows="3" class="form-control" placeholder="Mistaken transaction, returned item withdrawn by customer..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep Active</button>
                <button type="submit" class="btn btn-danger px-4 fw-semibold">Confirm Reverse</button>
            </div>
        </form>
    </div>
</div>
