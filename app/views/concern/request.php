<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Customer Product Requests</h4>
            <p class="text-secondary small mb-0">Track order inquiries, customer sample requests, and advance bookings.</p>
        </div>
        <?php if (\App\Core\Auth::can('request', 'add')): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddRequest">
            <i class="bi bi-file-earmark-plus me-1"></i> New Product Request
        </button>
        <?php endif; ?>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Customer Requests & Inquiries (Excel & PDF)';
    $exportSubtitle = 'Download customer bookings, product inquiries, required quantities & pipeline values';
    $exportUrl = \App\Core\Controller::url('/m/request/export');
    $prefix = 'req_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i> Request Pipeline</h6>
            <span class="badge bg-light text-dark border"><?= count($requests) ?> Requests</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Request No</th>
                        <th>Customer</th>
                        <th>Requested Variant</th>
                        <th>Requested Qty</th>
                        <th>Available Stock</th>
                        <th>Target Delivery</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No requests recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($r['request_no']) ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($r['customer_name']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($r['variant_name']) ?></div>
                                    <small class="badge bg-light text-dark border"><?= htmlspecialchars($r['sku_code']) ?></small>
                                </td>
                                <td><span class="fw-bold text-primary"><?= number_format($r['requested_qty'], 2) ?></span></td>
                                <td>
                                    <span class="badge <?= $r['current_stock'] >= $r['requested_qty'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= number_format($r['current_stock'], 2) ?> In Stock
                                    </span>
                                </td>
                                <td><?= date('d M Y', strtotime($r['expected_date'])) ?></td>
                                <td><span class="badge bg-info text-dark"><?= ucfirst($r['status']) ?></span></td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewRequestModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Request Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (\App\Core\Auth::can('request', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditRequestModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Request">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('request', 'delete')): ?>
                                        <form action="<?= \App\Core\Controller::url('/m/request/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete customer request <?= htmlspecialchars($r['request_no']) ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Request">
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

<div class="modal fade" id="modalAddRequest" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/request/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">New Customer Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Customer *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Requested Variant *</label>
                    <select name="variant_id" class="form-select" required>
                        <option value="">Select Variant</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['sku_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Quantity Needed *</label>
                        <input type="number" step="0.01" name="requested_qty" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Target Delivery Date</label>
                        <input type="date" name="expected_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notes / Special Instructions</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Custom packaging or barcode tagging"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Request -->
<div class="modal fade" id="modalEditRequest" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/request/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_request_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Customer Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Request No</label>
                    <input type="text" id="edit_request_no" class="form-control font-monospace" readonly>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Quantity Needed *</label>
                        <input type="number" step="0.01" name="requested_qty" id="edit_requested_qty" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Target Delivery Date</label>
                        <input type="date" name="expected_date" id="edit_expected_date" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Status *</label>
                    <select name="status" id="edit_request_status" class="form-select" required>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="allocated">Allocated</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notes / Special Instructions</label>
                    <textarea name="notes" id="edit_request_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Request -->
<div class="modal fade" id="modalViewRequest" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-file-earmark-text"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Customer Request Details</h6>
                        <small class="badge bg-dark font-monospace" id="view_request_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Requested Quantity</span>
                        <span class="fw-bold fs-5 text-primary font-monospace" id="view_requested_qty">0</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Current Stock</span>
                        <span class="badge" id="view_request_stock_badge">In Stock</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-person text-primary me-2"></i>Customer:</span>
                        <strong class="text-dark" id="view_request_customer">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-tag text-primary me-2"></i>Requested Variant:</span>
                        <strong class="text-dark" id="view_request_variant">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-upc-scan text-primary me-2"></i>SKU Code:</span>
                        <span class="badge bg-light text-dark border font-monospace" id="view_request_sku">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-check text-primary me-2"></i>Target Delivery:</span>
                        <span class="font-monospace text-dark" id="view_request_date">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-info-circle text-primary me-2"></i>Status:</span>
                        <span class="badge bg-info text-dark" id="view_request_status">Pending</span>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1"><i class="bi bi-chat-quote text-primary me-2"></i>Notes / Special Instructions:</span>
                        <div class="text-dark p-2 bg-light rounded" id="view_request_notes">None</div>
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
function openViewRequestModal(r) {
    if (!r) return;
    document.getElementById('view_request_no').innerText = r.request_no || '';
    document.getElementById('view_request_customer').innerText = r.customer_name || '-';
    document.getElementById('view_request_variant').innerText = r.variant_name || '-';
    document.getElementById('view_request_sku').innerText = r.sku_code || '-';
    document.getElementById('view_requested_qty').innerText = parseFloat(r.requested_qty || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('view_request_date').innerText = r.expected_date ? r.expected_date.substring(0, 10) : '-';
    document.getElementById('view_request_status').innerText = (r.status || 'pending').toUpperCase();
    document.getElementById('view_request_notes').innerText = r.notes || 'None';

    const stockBadge = document.getElementById('view_request_stock_badge');
    const inStock = (parseFloat(r.current_stock || 0) >= parseFloat(r.requested_qty || 0));
    stockBadge.className = inStock ? 'badge bg-success' : 'badge bg-warning text-dark';
    stockBadge.innerText = parseFloat(r.current_stock || 0).toFixed(2) + ' In Stock';

    const modal = new bootstrap.Modal(document.getElementById('modalViewRequest'));
    modal.show();
}
function openEditRequestModal(r) {
    if (!r) return;
    document.getElementById('edit_request_id').value = r.id || '';
    document.getElementById('edit_request_no').value = r.request_no || '';
    document.getElementById('edit_requested_qty').value = parseFloat(r.requested_qty || 0).toFixed(2);
    document.getElementById('edit_expected_date').value = r.expected_date ? r.expected_date.substring(0, 10) : '';
    document.getElementById('edit_request_status').value = r.status || 'pending';
    document.getElementById('edit_request_notes').value = r.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditRequest'));
    modal.show();
}
</script>
