<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Dispatch & Logistics Management</h4>
            <p class="text-secondary small mb-0">Record transport logistics, vehicle registrations, and airway bill / courier tracking numbers.</p>
        </div>
        <?php if (\App\Core\Auth::can('dispatch', 'add')): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddDispatch">
            <i class="bi bi-send me-1"></i> New Dispatch Log
        </button>
        <?php endif; ?>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Dispatch & Logistics Records (Excel & PDF)';
    $exportSubtitle = 'Download shipment manifests, freight carriers, vehicle numbers & consignment tracking registries';
    $exportUrl = \App\Core\Controller::url('/m/dispatch/export');
    $prefix = 'disp_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-truck-front me-2 text-primary"></i> Dispatches & Shipments</h6>
            <span class="badge bg-light text-dark border"><?= count($dispatches) ?> Shipments</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Dispatch No</th>
                        <th>Outward Gate Pass</th>
                        <th>Customer</th>
                        <th>Transporter</th>
                        <th>Vehicle No</th>
                        <th>Tracking / LR No</th>
                        <th>Dispatch Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dispatches)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No dispatch logs recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dispatches as $d): ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($d['dispatch_no']) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($d['outward_no']) ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($d['customer_name']) ?></td>
                                <td><?= htmlspecialchars($d['transporter_name'] ?: '-') ?></td>
                                <td><span class="badge bg-light text-dark font-monospace"><?= htmlspecialchars($d['vehicle_no'] ?: '-') ?></span></td>
                                <td><span class="font-monospace text-primary"><?= htmlspecialchars($d['tracking_no'] ?: '-') ?></span></td>
                                <td><?= date('d M Y', strtotime($d['dispatch_date'])) ?></td>
                                <td>
                                    <span class="badge <?= $d['status'] === 'delivered' ? 'bg-success' : 'bg-primary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $d['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewDispatchModal(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Dispatch Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditDispatchModal(<?= json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Dispatch">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="<?= \App\Core\Controller::url('/m/dispatch/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete dispatch record <?= htmlspecialchars($d['dispatch_no']) ?>? Outward status will revert to Prepared.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Dispatch">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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

<div class="modal fade" id="modalAddDispatch" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/dispatch/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Record Dispatch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Outward Gate Pass *</label>
                    <select name="outward_id" class="form-select" required>
                        <option value="">Select Prepared Outward Consignment</option>
                        <?php foreach ($outwards as $ow): ?>
                            <option value="<?= $ow['id'] ?>"><?= htmlspecialchars($ow['outward_no']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Transporter / Courier</label>
                        <input type="text" name="transporter_name" class="form-control" placeholder="e.g. VRL Logistics, DTDC">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Vehicle Number</label>
                        <input type="text" name="vehicle_no" class="form-control text-uppercase" placeholder="TN 38 AB 1234">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Tracking / LR Number</label>
                        <input type="text" name="tracking_no" class="form-control" placeholder="LR-987654">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Dispatch Date</label>
                        <input type="date" name="dispatch_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Driver phone, contact, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Dispatch</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Dispatch -->
<div class="modal fade" id="modalEditDispatch" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/dispatch/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_dispatch_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Dispatch Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Dispatch No</label>
                    <input type="text" id="edit_dispatch_no" class="form-control font-monospace" readonly>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Transporter / Courier</label>
                        <input type="text" name="transporter_name" id="edit_transporter_name" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Vehicle Number</label>
                        <input type="text" name="vehicle_no" id="edit_vehicle_no" class="form-control text-uppercase">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Tracking / LR Number</label>
                        <input type="text" name="tracking_no" id="edit_tracking_no" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Dispatch Date *</label>
                        <input type="date" name="dispatch_date" id="edit_dispatch_date" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Transit Status *</label>
                    <select name="status" id="edit_dispatch_status" class="form-select" required>
                        <option value="in_transit">In Transit</option>
                        <option value="delivered">Delivered</option>
                        <option value="returned">Returned</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notes</label>
                    <textarea name="notes" id="edit_dispatch_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Dispatch</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Dispatch -->
<div class="modal fade" id="modalViewDispatch" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-truck"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Dispatch Logistics Details</h6>
                        <small class="badge bg-dark font-monospace" id="view_dispatch_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Outward Ref</span>
                        <span class="badge bg-light text-dark border font-monospace fs-6" id="view_dispatch_outward">-</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Shipment Status</span>
                        <span class="badge" id="view_dispatch_status_badge">In Transit</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-person text-primary me-2"></i>Customer:</span>
                        <strong class="text-dark" id="view_dispatch_customer">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-truck text-primary me-2"></i>Transporter / Courier:</span>
                        <strong class="text-dark" id="view_dispatch_transporter">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-card-text text-primary me-2"></i>Vehicle Number:</span>
                        <span class="badge bg-light text-dark border font-monospace" id="view_dispatch_vehicle">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-upc text-primary me-2"></i>Tracking / LR #:</span>
                        <span class="font-monospace text-primary fw-bold" id="view_dispatch_tracking">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-event text-primary me-2"></i>Dispatch Date:</span>
                        <span class="font-monospace text-dark" id="view_dispatch_date">-</span>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1"><i class="bi bi-chat-left-text text-primary me-2"></i>Driver / Handover Notes:</span>
                        <div class="text-dark p-2 bg-light rounded" id="view_dispatch_notes">None</div>
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
function openViewDispatchModal(d) {
    if (!d) return;
    document.getElementById('view_dispatch_no').innerText = d.dispatch_no || '';
    document.getElementById('view_dispatch_outward').innerText = d.outward_no || '-';
    document.getElementById('view_dispatch_customer').innerText = d.customer_name || '-';
    document.getElementById('view_dispatch_transporter').innerText = d.transporter_name || '-';
    document.getElementById('view_dispatch_vehicle').innerText = d.vehicle_no || '-';
    document.getElementById('view_dispatch_tracking').innerText = d.tracking_no || '-';
    document.getElementById('view_dispatch_date').innerText = d.dispatch_date ? d.dispatch_date.substring(0, 10) : '-';
    document.getElementById('view_dispatch_notes').innerText = d.notes || 'None';

    const statusBadge = document.getElementById('view_dispatch_status_badge');
    if (d.status === 'delivered') {
        statusBadge.className = 'badge bg-success';
        statusBadge.innerText = 'Delivered';
    } else {
        statusBadge.className = 'badge bg-primary';
        statusBadge.innerText = 'In Transit';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalViewDispatch'));
    modal.show();
}
function openEditDispatchModal(d) {
    if (!d) return;
    document.getElementById('edit_dispatch_id').value = d.id || '';
    document.getElementById('edit_dispatch_no').value = d.dispatch_no || '';
    document.getElementById('edit_transporter_name').value = d.transporter_name || '';
    document.getElementById('edit_vehicle_no').value = d.vehicle_no || '';
    document.getElementById('edit_tracking_no').value = d.tracking_no || '';
    document.getElementById('edit_dispatch_date').value = d.dispatch_date ? d.dispatch_date.substring(0, 10) : '';
    document.getElementById('edit_dispatch_status').value = d.status || 'in_transit';
    document.getElementById('edit_dispatch_notes').value = d.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditDispatch'));
    modal.show();
}
</script>
