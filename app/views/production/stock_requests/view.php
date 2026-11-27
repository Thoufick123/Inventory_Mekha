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
            <!-- Action: Cancel / Reject -->
            <?php if ((\App\Core\Auth::can('request', 'delete') || \App\Core\Auth::can('request', 'edit')) && !in_array($request['status'], ['RECEIVED', 'SHORTAGE', 'CANCELLED', 'REJECTED'], true)): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelRequest">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Request Details Overview -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-box-arrow-right me-1 text-primary"></i> Source Origin</div>
                <div class="fw-bold text-dark fs-6">
                    <?= htmlspecialchars($request['source_concern_name'] ?: ($request['concern_name'] ?: ($request['from_location_type'] . ' #' . (int)$request['from_location_id']))) ?>
                    <span class="badge bg-light text-secondary border ms-1 small"><?= htmlspecialchars($request['from_location_type']) ?></span>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-box-arrow-in-left me-1 text-success"></i> Destination Target</div>
                <div class="fw-bold text-dark fs-6">
                    <?= htmlspecialchars($request['destination_concern_name'] ?: ($request['concern_name'] ?: ($request['to_location_type'] . ' #' . (int)$request['to_location_id']))) ?>
                    <span class="badge bg-light text-secondary border ms-1 small"><?= htmlspecialchars($request['to_location_type']) ?></span>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3">
                <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bi bi-chat-left-text me-1 text-dark"></i> Notes / Purpose</div>
                <div class="text-dark small">
                    <?= trim((string)($request['notes'] ?? '')) !== '' ? nl2br(htmlspecialchars($request['notes'])) : '<span class="text-muted">No notes provided.</span>' ?>
                </div>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['variant_name']) ?></div>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($item['product_code']) ?> (<?= htmlspecialchars($item['size'] ?? 'Standard') ?>)</small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= number_format((float)($item['live_source_available'] ?? 0), 2) ?> in store</span>
                            </td>
                            <td class="text-center fw-bold"><?= number_format((float)$item['requested_qty'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Staff Reply / Notes -->
    <?php
        $hasReplied = false;
        foreach ($audits as $a) {
            if (($a['action'] ?? '') === 'REPLY') {
                $hasReplied = true;
                break;
            }
        }
    ?>
    <?php if (!$hasReplied && !in_array($request['status'], ['REPLIED', 'CANCELLED', 'REJECTED'], true)): ?>
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-reply me-2 text-primary"></i>Staff Reply / Notes</h6>
        </div>
        <div class="card-body p-3">
            <form method="POST" action="<?= \App\Core\Controller::url('/production/stock-requests/reply') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                <div class="mb-2">
                    <textarea name="reply_notes" rows="2" class="form-control form-control-sm" placeholder="Enter staff reply or notes..." required></textarea>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold">
                        <i class="bi bi-send me-1"></i> Save Reply
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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
                            <span class="badge <?= $a['action'] === 'REPLY' ? 'bg-primary' : 'bg-dark' ?> mt-1"><?= htmlspecialchars($a['action']) ?></span>
                            <div class="flex-grow-1">
                                <div class="small fw-bold text-dark"><?= nl2br(htmlspecialchars($a['notes'] ?? 'Action performed')) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($a['actor_name'] ?? 'System') ?> • <?= htmlspecialchars($a['created_at']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
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
