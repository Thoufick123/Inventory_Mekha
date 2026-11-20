<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Outward Consignments & Gate Passes</h4>
            <p class="text-secondary small mb-0">Packaging and warehouse dispatch clearance. Automatically deducts live inventory stock via <code>StockService::move()</code>.</p>
        </div>
        <button class="btn btn-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddOutward">
            <i class="bi bi-box-arrow-up-right me-1"></i> New Outward Consignment
        </button>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Outward Deliveries (Excel & PDF)';
    $exportSubtitle = 'Download warehouse outward gate passes, dispatches to customers, transport challans & quantity audits';
    $exportUrl = \App\Core\Controller::url('/m/outward/export');
    $prefix = 'outw_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-arrow-up-right me-2 text-danger"></i> Outward Records</h6>
            <span class="badge bg-light text-dark border"><?= count($outwards) ?> Consignments</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Outward & Gate Pass</th>
                        <th>Recipient / Destination</th>
                        <th>Logistics / Vehicle</th>
                        <th>Outward Date</th>
                        <th>Location</th>
                        <th>Quantity Deducted</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outwards)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No outward consignments generated.</td></tr>
                    <?php else: ?>
                        <?php foreach ($outwards as $o): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark font-monospace"><?= htmlspecialchars($o['outward_no']) ?></span>
                                    <?php if (!empty($o['gate_pass_no'])): ?>
                                        <div class="mt-1"><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 font-monospace"><?= htmlspecialchars($o['gate_pass_no']) ?></span></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($o['transfer_type'] ?? '') === 'concern' || !empty($o['destination_concern_id'])): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 mb-1 d-inline-block">
                                            <i class="bi bi-building me-1"></i> Unit Transfer
                                        </span>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($o['dest_concern_name'] ?? 'Sister Concern') ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($o['dest_concern_code'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 mb-1 d-inline-block">
                                            <i class="bi bi-person me-1"></i> Customer
                                        </span>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($o['customer_name'] ?? 'General Customer') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($o['vehicle_number']) || !empty($o['transporter_name'])): ?>
                                        <div class="fw-semibold text-dark"><i class="bi bi-truck me-1 text-primary"></i><?= htmlspecialchars($o['vehicle_number'] ?: 'Carrier') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($o['transporter_name'] ?: ($o['driver_name'] ?? '')) ?></small>
                                        <?php if (!empty($o['lr_number'])): ?>
                                            <div class="small font-monospace text-secondary">LR: <?= htmlspecialchars($o['lr_number']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Standard Transport</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($o['outward_date'])) ?></td>
                                <td><?= htmlspecialchars($o['location']) ?></td>
                                <td>
                                    <span class="text-danger fw-bold fs-6">
                                        -<?= number_format($o['total_qty'], 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $ost = strtoupper($o['status'] ?? 'PREPARED');
                                        if ($ost === 'RECEIVED'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                            <i class="bi bi-check-circle me-1"></i> Received
                                        </span>
                                    <?php elseif ($ost === 'DISPATCHED'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-truck me-1"></i>In Transit</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= ucfirst(strtolower($ost)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <?php if (in_array(strtoupper($o['status'] ?? ''), ['DRAFT', 'PREPARED'])): ?>
                                            <form action="<?= \App\Core\Controller::url('/m/outward/dispatch') ?>" method="POST" class="d-inline" onsubmit="return confirm('Confirm vehicle departure & dispatch Outward <?= htmlspecialchars($o['outward_no']) ?>? Stock will transition into IN_TRANSIT.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-primary shadow-sm" title="Dispatch Outward Gate Pass">
                                                    <i class="bi bi-send me-1"></i> Dispatch
                                                </button>
                                            </form>
                                        <?php elseif (strtoupper($o['status'] ?? '') === 'DISPATCHED'): ?>
                                            <form action="<?= \App\Core\Controller::url('/m/outward/receive') ?>" method="POST" class="d-inline" onsubmit="return confirm('Reconcile destination arrival for Outward <?= htmlspecialchars($o['outward_no']) ?>? Stock will transition from IN_TRANSIT to AVAILABLE.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success shadow-sm" title="Reconcile Goods Receipt">
                                                    <i class="bi bi-check2-all me-1"></i> Receive
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewOutwardModal(<?= json_encode($o, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Consignment Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditOutwardModal(<?= json_encode($o, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Outward">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="<?= \App\Core\Controller::url('/m/outward/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete outward consignment <?= htmlspecialchars($o['outward_no']) ?>? This will restore stock.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Outward">
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

<div class="modal fade" id="modalAddOutward" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/outward/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">Create Outward Gate Pass</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($isSuperAdmin && empty($selectedCid)): ?>
                    <div class="mb-3 p-2 bg-light border rounded">
                        <label class="form-label small fw-bold text-danger text-uppercase mb-1">
                            <i class="bi bi-box-arrow-up-right me-1"></i> From Concern (Source Unit) *
                        </label>
                        <select name="concern_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Source Concern --</option>
                            <?php foreach ($allConcerns as $ac): ?>
                                <option value="<?= $ac['id'] ?>"><?= htmlspecialchars($ac['name']) ?> (<?= htmlspecialchars($ac['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Destination Type Selector -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-1">Outward Destination Type *</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="transfer_type" id="tt_customer" value="customer" checked onchange="toggleOutwardDestType()">
                        <label class="btn btn-outline-danger btn-sm" for="tt_customer"><i class="bi bi-person me-1"></i> Customer Dispatch</label>

                        <input type="radio" class="btn-check" name="transfer_type" id="tt_concern" value="concern" onchange="toggleOutwardDestType()">
                        <label class="btn btn-outline-danger btn-sm" for="tt_concern"><i class="bi bi-building me-1"></i> Inter-Unit / Concern Transfer</label>
                    </div>
                </div>

                <!-- Customer Selection -->
                <div class="mb-3" id="div_customer_select">
                    <label class="form-label small fw-semibold">Customer *</label>
                    <select name="customer_id" id="outward_customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Destination Concern Selection -->
                <div class="mb-3 d-none" id="div_concern_select">
                    <label class="form-label small fw-semibold text-primary"><i class="bi bi-building me-1"></i> Destination Concern / Receiving Unit *</label>
                    <select name="destination_concern_id" id="outward_dest_concern_id" class="form-select">
                        <option value="">-- Choose Destination Concern --</option>
                        <?php foreach ($allConcerns as $c): ?>
                            <?php if (empty($selectedCid) || $c['id'] != $selectedCid): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-1">
                        <i class="bi bi-shield-check text-success me-1"></i> Dispatched products will only reflect and be inwarded in this chosen concern.
                    </small>
                </div>

                <div class="mb-3" id="div_invoice_select">
                    <label class="form-label small fw-semibold">Sales Invoice Ref</label>
                    <select name="sales_invoice_id" class="form-select">
                        <option value="">None (Sample / Transfer / Non-billed delivery)</option>
                        <?php foreach ($invoices as $inv): ?>
                            <option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_no']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Approved Production Batches Consolidation (Multi-Batch Outward Manifest) -->
                <?php if (!empty($approvedBatches)): ?>
                    <div class="mb-3 p-2 bg-light border rounded">
                        <label class="form-label small fw-bold text-success text-uppercase mb-1">
                            <i class="bi bi-boxes me-1"></i> Consolidate Approved Production Batches (Optional)
                        </label>
                        <div class="table-responsive bg-white rounded border" style="max-height: 140px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <tbody>
                                    <?php foreach ($approvedBatches as $ab): ?>
                                        <tr>
                                            <td style="width: 30px;" class="ps-2">
                                                <input class="form-check-input" type="checkbox" name="selected_batches[]" value="<?= $ab['id'] ?>">
                                            </td>
                                            <td>
                                                <span class="badge bg-dark font-monospace"><?= htmlspecialchars($ab['batch_no']) ?></span>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($ab['variant_name']) ?></div>
                                            </td>
                                            <td class="text-end pe-2">
                                                <span class="fw-bold text-primary"><?= number_format($ab['batch_qty'], 2) ?> pcs</span>
                                                <small class="d-block text-muted"><?= (int)$ab['total_boxes'] ?> boxes</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted d-block mt-1">Check one or more approved batches to load onto this truck / gate pass.</small>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Product Variant (Direct/Sample)</label>
                    <select name="variant_id" class="form-select">
                        <option value="">Select Variant (or use batches above)</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>">
                                <?= htmlspecialchars($v['sku_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?> (On Hand: <?= $v['stock_qty'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Quantity to Outward</label>
                        <input type="number" step="0.01" name="qty" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Warehouse Bay / Location</label>
                        <input type="text" name="location" class="form-control" value="Main Store">
                    </div>
                </div>

                <!-- Logistics & Carrier Details -->
                <div class="p-2.5 bg-light rounded border mb-3">
                    <h6 class="small fw-bold text-secondary text-uppercase mb-2"><i class="bi bi-truck me-1"></i> Logistics & Gate Pass Details</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Vehicle / Truck No</label>
                            <input type="text" name="vehicle_number" class="form-control form-control-sm" placeholder="TN-01-AB-1234">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Transporter / Logistics</label>
                            <input type="text" name="transporter_name" class="form-control form-control-sm" placeholder="VRL / SafeExpress / Self">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control form-control-sm" placeholder="Driver full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Driver Contact</label>
                            <input type="text" name="driver_contact" class="form-control form-control-sm" placeholder="+91 9876543210">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Lorry Receipt (LR / Bilty) Number</label>
                            <input type="text" name="lr_number" class="form-control form-control-sm" placeholder="LR-XXXXXX tracking code">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Outward Date</label>
                    <input type="date" name="outward_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Deduct Stock & Issue Gate Pass</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Outward -->
<div class="modal fade" id="modalEditOutward" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/outward/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_outward_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Outward Gate Pass</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Outward No</label>
                    <input type="text" id="edit_outward_no" class="form-control font-monospace" readonly>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Outward Date *</label>
                        <input type="date" name="outward_date" id="edit_outward_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Status *</label>
                        <select name="status" id="edit_outward_status" class="form-select" required>
                            <option value="prepared">Prepared</option>
                            <option value="dispatched">Dispatched</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Remarks / Notes</label>
                    <textarea name="notes" id="edit_outward_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Gate Pass</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Outward -->
<div class="modal fade" id="modalViewOutward" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Outward Gate Pass Details</h6>
                        <small class="badge bg-dark font-monospace" id="view_outward_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Total Quantity Issued</span>
                        <span class="fw-bold fs-5 text-danger font-monospace" id="view_outward_qty">-0.00</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Status</span>
                        <span class="badge" id="view_outward_status_badge">Prepared</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-person text-primary me-2"></i>Customer:</span>
                        <strong class="text-dark" id="view_outward_customer">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-receipt text-primary me-2"></i>Sales Invoice #:</span>
                        <span class="badge bg-light text-dark border font-monospace" id="view_outward_inv">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-geo-alt text-primary me-2"></i>Warehouse Bay:</span>
                        <span class="text-dark fw-semibold" id="view_outward_loc">Main Store</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-check text-primary me-2"></i>Issue Date:</span>
                        <span class="font-monospace text-dark" id="view_outward_date">-</span>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1"><i class="bi bi-chat-left-text text-primary me-2"></i>Remarks / Notes:</span>
                        <div class="text-dark p-2 bg-light rounded" id="view_outward_notes">None</div>
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
function toggleOutwardDestType() {
    const isConcern = document.getElementById('tt_concern').checked;
    const custDiv = document.getElementById('div_customer_select');
    const custInput = document.getElementById('outward_customer_id');
    const conDiv = document.getElementById('div_concern_select');
    const conInput = document.getElementById('outward_dest_concern_id');

    if (isConcern) {
        custDiv.classList.add('d-none');
        custInput.removeAttribute('required');
        conDiv.classList.remove('d-none');
        conInput.setAttribute('required', 'required');
    } else {
        conDiv.classList.add('d-none');
        conInput.removeAttribute('required');
        custDiv.classList.remove('d-none');
        custInput.setAttribute('required', 'required');
    }
}

function openViewOutwardModal(o) {
    if (!o) return;
    const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
    setTxt('view_outward_no', o.outward_no || '');
    const destName = (o.transfer_type === 'concern' || o.destination_concern_id) 
        ? 'Unit Transfer: ' + (o.dest_concern_name || 'Sister Concern') + (o.dest_concern_code ? ' (' + o.dest_concern_code + ')' : '')
        : (o.customer_name || 'General Customer');
    setTxt('view_outward_customer', destName);
    setTxt('view_outward_inv', o.invoice_no || 'None');
    setTxt('view_outward_loc', o.location || 'Main Store');
    setTxt('view_outward_date', o.outward_date ? o.outward_date.substring(0, 10) : '-');
    setTxt('view_outward_qty', '-' + parseFloat(o.total_qty || 0).toLocaleString('en-IN', {minimumFractionDigits: 2}));
    setTxt('view_outward_notes', o.notes || 'None');

    const statusBadge = document.getElementById('view_outward_status_badge');
    if (statusBadge) {
        if (o.status === 'received') {
            statusBadge.className = 'badge bg-info';
            statusBadge.innerText = 'Received / Inwarded';
        } else if (o.status === 'dispatched') {
            statusBadge.className = 'badge bg-success';
            statusBadge.innerText = 'Dispatched';
        } else {
            statusBadge.className = 'badge bg-warning text-dark';
            statusBadge.innerText = 'Prepared';
        }
    }

    const modalEl = document.getElementById('modalViewOutward');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}
function openEditOutwardModal(o) {
    if (!o) return;
    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
    setVal('edit_outward_id', o.id || '');
    setVal('edit_outward_no', o.outward_no || '');
    setVal('edit_outward_date', o.outward_date ? o.outward_date.substring(0, 10) : '');
    setVal('edit_outward_status', o.status || 'prepared');
    setVal('edit_outward_notes', o.notes || '');

    const modalEl = document.getElementById('modalEditOutward');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}
</script>
