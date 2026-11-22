<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= \App\Core\Controller::url('/production/stock-requests') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Requisition <?= htmlspecialchars($request['request_no']) ?></h4>
                <small class="text-muted">Initiated by <?= htmlspecialchars($request['creator_name'] ?? 'Staff') ?> on <?= htmlspecialchars($request['created_at']) ?></small>
            </div>
            <span class="badge <?= match($request['status']) { 'RECEIVED' => 'bg-success', 'DISPATCHED', 'IN_TRANSIT' => 'bg-info text-dark', 'SHORTAGE' => 'bg-warning text-dark', 'CANCELLED', 'REJECTED' => 'bg-danger', default => 'bg-secondary' } ?> fs-6">
                <?= htmlspecialchars($request['status']) ?>
            </span>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($request['dispatch_challan_no'])): ?>
                <a href="<?= \App\Core\Controller::url('/production/stock-requests/print/' . $request['id']) ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i> Print Challan
                </a>
            <?php endif; ?>

            <!-- Action: Dispatch stock -->
            <?php if ((\App\Core\Auth::can('request', 'edit') || \App\Core\Auth::can('request', 'add')) && in_array($request['status'], ['PENDING', 'APPROVED'], true)): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDispatchStock">
                    <i class="bi bi-truck me-1"></i> Dispatch Stock (Move to In-Transit)
                </button>
            <?php endif; ?>

            <!-- Action: Confirm Receipt -->
            <?php if ((\App\Core\Auth::can('request', 'edit') || \App\Core\Auth::can('request', 'add')) && in_array($request['status'], ['DISPATCHED', 'IN_TRANSIT'], true)): ?>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalConfirmReceipt">
                    <i class="bi bi-box-seam me-1"></i> Confirm Receipt at Destination
                </button>
            <?php endif; ?>

            <!-- Action: Cancel / Reject -->
            <?php if ((\App\Core\Auth::can('request', 'delete') || \App\Core\Auth::can('request', 'edit')) && !in_array($request['status'], ['RECEIVED', 'SHORTAGE', 'CANCELLED', 'REJECTED'], true)): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelRequest">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Logistics Details Overview -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-box-arrow-right me-1 text-primary"></i> Source Origin</div>
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($request['from_location_type']) ?> #<?= (int)$request['from_location_id'] ?></div>
                <small class="text-secondary d-block">Debits Live AVAILABLE stock upon dispatch.</small>
                <?php if (!empty($request['dispatched_at'])): ?>
                    <div class="mt-2 pt-2 border-top small">
                        <span class="text-muted">Dispatched By:</span> <strong><?= htmlspecialchars($request['dispatcher_name'] ?? 'Dispatcher') ?></strong>
                        <div class="text-muted font-monospace"><?= htmlspecialchars($request['dispatched_at']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-box-arrow-in-left me-1 text-success"></i> Destination Target</div>
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($request['to_location_type']) ?> #<?= (int)$request['to_location_id'] ?></div>
                <small class="text-secondary d-block">Credits destination stock upon receipt confirmation.</small>
                <?php if (!empty($request['received_at'])): ?>
                    <div class="mt-2 pt-2 border-top small">
                        <span class="text-muted">Received By:</span> <strong><?= htmlspecialchars($request['receiver_name'] ?? 'Receiver') ?></strong>
                        <div class="text-muted font-monospace"><?= htmlspecialchars($request['received_at']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-receipt me-1 text-dark"></i> Challan & Fulfillment</div>
                <?php if (!empty($request['dispatch_challan_no'])): ?>
                    <div class="fw-bold text-dark font-monospace fs-6"><?= htmlspecialchars($request['dispatch_challan_no']) ?></div>
                <?php else: ?>
                    <div class="text-muted small">No Challan Issued Yet</div>
                <?php endif; ?>
                <div class="mt-2 small">
                    <span class="text-secondary">In-Transit Balance:</span>
                    <strong class="text-warning"><?= number_format((float)$request['in_transit_qty'], 2) ?> units</strong>
                </div>
                <?php if ($request['shortage_qty'] > 0): ?>
                    <div class="small text-danger fw-bold">
                        Transit Shortage: -<?= number_format((float)$request['shortage_qty'], 2) ?> units
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-boxes me-2 text-primary"></i>Requisition Garment Line Items</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Variant Style</th>
                        <th class="text-center">Source Live Available</th>
                        <th class="text-center">Requested</th>
                        <th class="text-center">Dispatched</th>
                        <th class="text-center">In-Transit</th>
                        <th class="text-center">Received</th>
                        <th class="text-center">Shortage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['variant_name']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($item['sku_code']) ?> (<?= htmlspecialchars($item['size'] ?? 'Standard') ?>)</small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= number_format((float)($item['live_source_available'] ?? 0), 2) ?> in store</span>
                            </td>
                            <td class="text-center fw-bold"><?= number_format((float)$item['requested_qty'], 2) ?></td>
                            <td class="text-center text-primary"><?= number_format((float)$item['dispatched_qty'], 2) ?></td>
                            <td class="text-center">
                                <?php if ($item['in_transit_qty'] > 0): ?>
                                    <span class="badge bg-warning text-dark"><?= number_format((float)$item['in_transit_qty'], 2) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-success fw-bold"><?= number_format((float)$item['received_qty'], 2) ?></td>
                            <td class="text-center">
                                <?php if ($item['shortage_qty'] > 0): ?>
                                    <span class="badge bg-danger">-<?= number_format((float)$item['shortage_qty'], 2) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit Timeline -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Requisition Audit Trail</h6>
        </div>
        <div class="card-body p-3">
            <?php if (empty($audits)): ?>
                <div class="text-muted small">No audit actions recorded.</div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($audits as $a): ?>
                        <div class="d-flex align-items-start gap-3 mb-3 border-bottom pb-2">
                            <span class="badge bg-dark mt-1"><?= htmlspecialchars($a['action']) ?></span>
                            <div class="flex-grow-1">
                                <div class="small fw-bold text-dark"><?= htmlspecialchars($a['notes'] ?? 'Action performed') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($a['actor_name'] ?? 'System') ?> • <?= htmlspecialchars($a['created_at']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Dispatch Stock -->
<div class="modal fade" id="modalDispatchStock" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?= \App\Core\Controller::url('/production/stock-requests/send') ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-truck me-2"></i>Dispatch Stock to In-Transit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-secondary small mb-3">
                    Specify the quantities to dispatch for each line item. Stock will be atomically moved from source <code>AVAILABLE</code> into <code>IN_TRANSIT</code> under a new Dispatch Challan.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light small">
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Requested</th>
                                <th class="text-center">Available at Source</th>
                                <th style="width: 30%;">Dispatch Qty *</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $remaining = (float)$item['requested_qty'] - (float)$item['dispatched_qty'];
                                if ($remaining <= 0) continue;
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars($item['variant_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($item['sku_code']) ?></small>
                                    </td>
                                    <td class="text-center"><?= number_format($remaining, 2) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border"><?= number_format((float)($item['live_source_available'] ?? 0), 2) ?></span>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0.01" max="<?= $remaining ?>" name="dispatch_qty[<?= $item['id'] ?>]" class="form-control form-control-sm font-monospace" value="<?= $remaining ?>" required>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-send me-1"></i> Issue Challan & Move Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Confirm Receipt -->
<div class="modal fade" id="modalConfirmReceipt" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?= \App\Core\Controller::url('/production/stock-requests/receive') ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-box-seam me-2"></i>Confirm Receipt at Destination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-secondary small mb-3">
                    Verify received physical stock at destination. Quantities entered will be moved from <code>IN_TRANSIT</code> into destination <code>AVAILABLE</code> stock. Any shortfall is logged as a shortage discrepancy.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light small">
                            <tr>
                                <th>Item</th>
                                <th class="text-center">In-Transit Qty</th>
                                <th style="width: 35%;">Physically Received Qty *</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $inTransit = (float)$item['in_transit_qty'];
                                if ($inTransit <= 0) continue;
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars($item['variant_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($item['sku_code']) ?></small>
                                    </td>
                                    <td class="text-center fw-bold text-warning"><?= number_format($inTransit, 2) ?></td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="<?= $inTransit ?>" name="received_qty[<?= $item['id'] ?>]" class="form-control form-control-sm font-monospace" value="<?= $inTransit ?>" required>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4 fw-semibold"><i class="bi bi-check-circle me-1"></i> Confirm Receipt & Credit Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Cancel Request -->
<div class="modal fade" id="modalCancelRequest" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= \App\Core\Controller::url('/production/stock-requests/cancel') ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= $request['id'] ?>">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Cancel Requisition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-secondary small mb-3">
                    Cancelling this requisition will return any in-transit stock back to the source location's available balance.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Cancellation Reason</label>
                    <textarea name="cancel_reason" rows="3" class="form-control" placeholder="Requisition no longer needed, duplicate request..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep Active</button>
                <button type="submit" class="btn btn-danger px-4 fw-semibold">Confirm Cancel</button>
            </div>
        </form>
    </div>
</div>
