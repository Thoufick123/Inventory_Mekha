<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Customer Returns & Exchanges</h4>
            <p class="text-secondary small mb-0">Process customer returns and replacement exchanges. Re-enters returned stock into warehouse balance via <code>StockService::move()</code>.</p>
        </div>
        <?php if (\App\Core\Auth::can('return_exchange', 'add')): ?>
        <button class="btn btn-warning shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddReturn">
            <i class="bi bi-arrow-left-right me-1"></i> New Return / Exchange
        </button>
        <?php endif; ?>
    </div>

    <!-- Export Intelligence Toolbar -->
    <?php
    $exportTitle = 'Export Customer Returns & Exchanges (Excel & PDF)';
    $exportSubtitle = 'Download returned units, replacement variant exchanges, refunds & restock audit journals';
    $exportUrl = \App\Core\Controller::url('/m/return_exchange/export');
    $prefix = 'retexc_exp';
    include __DIR__ . '/../partials/export_card.php';
    ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-counterclockwise me-2 text-warning"></i> Return Transactions</h6>
            <span class="badge bg-light text-dark border"><?= count($returns) ?> Records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead>
                    <tr>
                        <th>Return No</th>
                        <th>Type</th>
                        <th>Customer</th>
                        <th>Returned Variant</th>
                        <th>Restocked (+Qty)</th>
                        <th>Exchange Replacement</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No returns or exchanges recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $r): ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($r['return_no']) ?></span></td>
                                <td>
                                    <span class="badge <?= $r['type'] === 'exchange' ? 'bg-info text-dark' : 'bg-secondary' ?>">
                                        <?= strtoupper($r['type']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($r['customer_name']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($r['returned_variant_name']) ?></div>
                                    <small class="badge bg-light text-dark border"><?= htmlspecialchars($r['returned_sku']) ?></small>
                                </td>
                                <td><span class="text-success fw-bold fs-6">+<?= number_format($r['returned_qty'], 2) ?></span></td>
                                <td>
                                    <?php if ($r['type'] === 'exchange' && $r['exchange_variant_name']): ?>
                                        <div class="text-dark small fw-semibold"><?= htmlspecialchars($r['exchange_variant_name']) ?></div>
                                        <small class="text-danger fw-bold">-<?= number_format($r['exchange_qty'], 2) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted small">None (Pure Return)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($r['reason'] ?: '-') ?></td>
                                <td><?= date('d M Y', strtotime($r['return_date'])) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick='openViewReturnModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Return Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (\App\Core\Auth::can('return_exchange', 'edit')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditReturnModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Return">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Core\Auth::can('return_exchange', 'delete')): ?>
                                        <form action="<?= \App\Core\Controller::url('/m/return_exchange/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete return record <?= htmlspecialchars($r['return_no']) ?>? This will reverse stock additions/deductions.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Return">
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

<div class="modal fade" id="modalAddReturn" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/return_exchange/store') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Process Return / Exchange</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Transaction Type</label>
                        <select name="type" id="ret_type" class="form-select" onchange="toggleExchangeFields(this)">
                            <option value="return">Customer Return (Restock)</option>
                            <option value="exchange">Exchange (Swap Variant)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Customer *</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Returned Variant (Adds back to Stock) *</label>
                    <select name="returned_variant_id" class="form-select" required>
                        <option value="">Select Variant Returned</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['sku_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Returned Quantity *</label>
                    <input type="number" step="0.01" name="returned_qty" class="form-control" required placeholder="0.00">
                </div>

                <div id="exchange_group" style="display: none;" class="p-3 bg-light rounded border mb-3">
                    <div class="fw-bold small text-primary mb-2">Replacement Exchange Item:</div>
                    <div class="mb-2">
                        <label class="form-label small">Exchange Variant Out</label>
                        <select name="exchange_variant_id" class="form-select">
                            <option value="">Select Replacement Variant</option>
                            <?php foreach ($variants as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['sku_code']) ?> &bull; <?= htmlspecialchars($v['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small">Exchange Quantity Out</label>
                        <input type="number" step="0.01" name="exchange_qty" class="form-control" placeholder="0.00">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Return Reason</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Wrong size, color issue, transit damage"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Process Return & Rebalance Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Return -->
<div class="modal fade" id="modalEditReturn" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= \App\Core\Controller::url('/m/return_exchange/update') ?>" method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="edit_return_id" value="">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1 text-primary"></i> Edit Return / Exchange Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Return No</label>
                    <input type="text" id="edit_return_no" class="form-control font-monospace" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Return Date *</label>
                    <input type="date" name="return_date" id="edit_return_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Return Reason / Notes</label>
                    <textarea name="reason" id="edit_return_reason" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Return Log</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Return -->
<div class="modal fade" id="modalViewReturn" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span style="width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                        <i class="bi bi-arrow-return-left"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Return / Replacement Exchange</h6>
                        <small class="badge bg-dark font-monospace" id="view_return_no"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-secondary small d-block">Transaction Type</span>
                        <span class="badge" id="view_return_type_badge">RETURN</span>
                    </div>
                    <div class="text-end">
                        <span class="text-secondary small d-block">Returned Qty Restocked</span>
                        <span class="fw-bold fs-5 text-success font-monospace" id="view_return_qty">+0.00</span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-person text-primary me-2"></i>Customer:</span>
                        <strong class="text-dark" id="view_return_customer">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-box-arrow-in-down text-primary me-2"></i>Returned Item:</span>
                        <strong class="text-dark" id="view_return_variant">-</strong>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-upc text-primary me-2"></i>Returned SKU:</span>
                        <span class="badge bg-light text-dark border font-monospace" id="view_return_sku">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between" id="view_exchange_row" style="display: none;">
                        <span class="text-muted"><i class="bi bi-arrow-repeat text-primary me-2"></i>Exchange Replacement:</span>
                        <span class="text-danger fw-semibold" id="view_exchange_info">-</span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span class="text-muted"><i class="bi bi-calendar-date text-primary me-2"></i>Return Date:</span>
                        <span class="font-monospace text-dark" id="view_return_date">-</span>
                    </li>
                    <li class="list-group-item px-0">
                        <span class="text-muted d-block mb-1"><i class="bi bi-chat-left-dots text-primary me-2"></i>Reason:</span>
                        <div class="text-dark p-2 bg-light rounded" id="view_return_reason">-</div>
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
function toggleExchangeFields(sel) {
    document.getElementById('exchange_group').style.display = sel.value === 'exchange' ? 'block' : 'none';
}

function openViewReturnModal(r) {
    if (!r) return;
    document.getElementById('view_return_no').innerText = r.return_no || '';
    document.getElementById('view_return_customer').innerText = r.customer_name || '-';
    document.getElementById('view_return_variant').innerText = r.returned_variant_name || '-';
    document.getElementById('view_return_sku').innerText = r.returned_sku || '-';
    document.getElementById('view_return_qty').innerText = '+' + parseFloat(r.returned_qty || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('view_return_date').innerText = r.return_date ? r.return_date.substring(0, 10) : '-';
    document.getElementById('view_return_reason').innerText = r.reason || 'None specified';

    const typeBadge = document.getElementById('view_return_type_badge');
    const excRow = document.getElementById('view_exchange_row');
    if (r.type === 'exchange') {
        typeBadge.className = 'badge bg-info text-dark';
        typeBadge.innerText = 'EXCHANGE';
        if (excRow) {
            excRow.style.display = 'flex';
            document.getElementById('view_exchange_info').innerText = (r.exchange_variant_name || '') + ' (-' + (r.exchange_qty || 0) + ')';
        }
    } else {
        typeBadge.className = 'badge bg-secondary';
        typeBadge.innerText = 'RETURN';
        if (excRow) excRow.style.display = 'none';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalViewReturn'));
    modal.show();
}

function openEditReturnModal(r) {
    if (!r) return;
    document.getElementById('edit_return_id').value = r.id || '';
    document.getElementById('edit_return_no').value = r.return_no || '';
    document.getElementById('edit_return_date').value = r.return_date ? r.return_date.substring(0, 10) : '';
    document.getElementById('edit_return_reason').value = r.reason || '';

    const modal = new bootstrap.Modal(document.getElementById('modalEditReturn'));
    modal.show();
}
</script>
